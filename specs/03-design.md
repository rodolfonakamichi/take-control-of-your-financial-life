# 03 — Design técnico

**Status:** Aprovado em 27/11/2026
**Deriva de:** [01-requirements.md](01-requirements.md), [02-domain.md](02-domain.md)

## 1. Stack

| Camada | Escolha | Por quê |
|---|---|---|
| Linguagem / framework | PHP 8.5 + Laravel 13 | Linguagem que o dono do projeto já domina; ecossistema completo (auth, filas, scheduler, testes) |
| Interface | Livewire 4 + Alpine.js + Tailwind CSS 4 | Telas reativas escrevendo PHP e Blade, sem SPA separada; Tailwind facilita o *mobile-first* (RNF-07) |
| Banco | SQLite (um arquivo) | Uso pessoal, um usuário, roda local; backup = copiar um arquivo |
| Planilhas | PhpSpreadsheet | Ler o `.xlsx` legado (RF-09) e gerar `.xlsx` (RF-17) |
| Gráficos | Chart.js | Leve, responsivo, funciona bem com Livewire (RF-12) |
| Testes | Pest | Sintaxe enxuta; nomes de teste carregam o ID do critério de aceite (RNF-04) |
| Execução | Docker Compose com FrankenPHP | A máquina tem PHP 8.0 sem Composer nem SQLite; Laravel atual exige PHP ≥ 8.2. O Docker isola isso e o FrankenPHP (servidor embutido, baseado no Caddy) serve o app e pode dar HTTPS local depois |

## 2. Arquitetura

```
┌────────────────────────────────────────────────────────────┐
│  Interface — app/Livewire, resources/views                 │
│  telas, formulários, gráficos                              │
├────────────────────────────────────────────────────────────┤
│  Aplicação — app/Application                               │
│  casos de uso: LancarDespesa, MarcarComoPago, MontarMes,   │
│  ImportarPlanilha, ExportarAno…  (orquestram, não calculam) │
├──────────────────────────────┬─────────────────────────────┤
│  Domínio — app/Domain        │  Infra — app/Infra           │
│  PHP puro, sem Laravel       │  Eloquent, PhpSpreadsheet,   │
│  Dinheiro, Competencia,      │  backup, relógio             │
│  todas as regras RD-xx/RV-xx │                              │
└──────────────────────────────┴─────────────────────────────┘
```

Regras:

- **Todo cálculo mora no domínio** (RNF-03). Tela, gráfico, exportação e alerta leem os mesmos números (CA-12.5).
- O domínio não conhece o Laravel, nem o banco, nem a data de hoje. O "mês atual" entra como parâmetro, vindo de um `Relogio` injetável, o que permite testar regras de passado e futuro (RD-16, CA-08.2).
- Totais **nunca** são gravados no banco: são sempre recalculados (RV-02). Com um usuário e alguns milhares de lançamentos, isso é instantâneo no SQLite.

### 2.1 Domínio — módulos

| Classe | Regras | Responsabilidade |
|---|---|---|
| `Dinheiro` | RNF-01, RNF-02 | Valor em centavos (`int`), soma/subtração, divisão em parcelas com resto, formatação `R$ 1.234,56` |
| `Competencia` | RD-11 | (ano, mês), aritmética de meses, comparação, "último dia do mês" |
| `CalculadoraResumo` | RD-01..04, RD-08, RD-09 | Recebe os itens do mês e devolve `ResumoMensal` |
| `LivroGuardado` | RD-03, RD-05, RV-07 | Total guardado por mês; valida se um movimento deixa algum mês negativo |
| `ResolvedorRecorrencia` | RD-07, CA-04.x | Dada a recorrência, seus valores vigentes e as exceções, devolve a ocorrência de um mês |
| `GeradorParcelas` | RD-06, RD-14, RD-16 | Gera as parcelas; decide quais parcelas uma alteração ou cancelamento pode tocar |
| `CalendarioFatura` | RD-11..13 | Data da compra → fatura (mês de fechamento) → data de vencimento |
| `MontadorFatura` | RD-14, RD-15 | Soma as parcelas de uma fatura e a transforma em item de despesa do mês do vencimento |
| `TotaisPorCategoria` | RD-10 | Abre faturas nas compras e soma por categoria; garante que bate com `ContasAPagar` |
| `DetectorAlertas` | RV-05, RF-14 | Gera a lista de alertas a partir dos resumos (nada é gravado) |
| `AgendaVencimentos` | RF-13 | Despesas a pagar vencidas e a vencer no horizonte |

### 2.2 Montagem de um mês

O mês é uma **visão montada**, não uma tabela. `MontarMes(competencia)` junta:

1. lançamentos avulsos e receitas da competência;
2. parcelas de parcelamentos com aquela competência;
3. ocorrências **virtuais** das recorrências ativas (ver 3.2);
4. faturas de cartão que **vencem** naquela competência;
5. movimentos do guardado.

Em seguida calcula o `ResumoMensal` e marca o mês como "projeção" se `competencia > mês atual` (CA-08.2). Por isso qualquer ano pode ser navegado sem "criar ano" (CA-08.3).

## 3. Modelo de dados

Convenções: valores em `*_centavos INTEGER`; competência em `CHAR(7)` no formato `AAAA-MM`, que ordena corretamente como texto; datas em `DATE`.
Status de pagamento é `pago_em` (nulo = a pagar). Em `lancamentos` ele já existe na v1.0, porque o RD-16 (alterar ou cancelar parcelamento) depende de saber se a parcela foi paga.

Os models Eloquent ficam em `app/Models` (convenção do Laravel). Quem converte entre models e objetos de domínio são os repositórios em `app/Infra/Persistencia`. O domínio nunca recebe um model.

### 3.1 Tabelas

| Tabela | Colunas principais | Entrega |
|---|---|---|
| `users` | uma única linha (o dono); só `password` é usado | v1.0 |
| `configuracoes` | `chave`, `valor` — `guardado_inicial_centavos`, `horizonte_vencimento_dias`, `pasta_backup` | v1.0 |
| `lancamentos` | `tipo` (despesa/receita), `descricao`, `valor_centavos`, `competencia`, `categoria_receita`, `origem` (avulso/parcelamento), `parcelamento_id`, `parcela_numero`, `pago_em`, `chave_importacao` *(única, nula)* | v1.0 |
| `recorrencias` | `descricao`, `inicio`, `fim` *(nulo)* | v1.0 |
| `recorrencia_valores` | `recorrencia_id`, `vigente_desde`, `valor_centavos` | v1.0 |
| `recorrencia_ocorrencias` | `recorrencia_id`, `competencia` *(único com o id)*, `valor_centavos` *(nulo = usa o vigente)* | v1.0 |
| `parcelamentos` | `descricao`, `valor_parcela_centavos`, `total_parcelas`, `primeira_competencia`, `cancelado_em` | v1.0 |
| `movimentos_guardado` | `competencia`, `tipo` (guardar/retirar), `valor_centavos`, `descricao`, `chave_importacao` | v1.0 |
| `categorias` | `nome` *(único)* | v1.1 |
| + colunas | `categoria_id` em lançamentos, recorrências, parcelamentos e compras; `pago_em` em ocorrências; `vencimento` em lançamentos; `dia_vencimento` em recorrências e parcelamentos | v1.1 |
| `cartoes` | `nome`, `dia_fechamento`, `dia_vencimento` | v1.2 |
| `compras_cartao` | `cartao_id`, `data_compra`, `descricao`, `categoria_id`, `valor_total_centavos`, `parcelas`, `cancelada_em` | v1.2 |
| `parcelas_cartao` | `compra_id`, `numero`, `valor_centavos`, `fatura_fechamento` | v1.2 |
| `faturas` | `cartao_id`, `fechamento` *(único com o cartão)*, `status`, `pago_em` — linha criada só quando a fatura é paga | v1.2 |

### 3.2 Por que recorrências são virtuais e parcelas são gravadas

- **Recorrência** não tem fim, então não dá para gravar todos os meses. O mês é calculado na hora:
  valor = exceção do mês (`recorrencia_ocorrencias.valor_centavos`) **ou** o `recorrencia_valores`
  vigente naquela competência. Uma linha de ocorrência só é criada quando algo naquele mês diverge
  do padrão: valor editado (CA-04.2) ou status pago (CA-10.1). Mudar o valor "daqui para frente"
  é inserir um novo `recorrencia_valores` (CA-04.3), o que preserva o passado sem copiar nada.
- **Parcelamento** e **compra no cartão** têm N finito: as parcelas são gravadas na criação. Isso
  torna RD-16 simples: alterar ou cancelar mexe só nas linhas com `competencia ≥ mês atual` e
  `status = a_pagar`.

## 4. Importação da planilha legada (RF-09)

Comando `php artisan financas:importar <arquivo.xlsx> [--simular]`. Com `--simular`, só gera o relatório.

**Leitura do layout:**
1. Cada aba cujo nome é um ano (`2025`, `2026`…) é um ano.
2. Blocos de mês são localizados procurando o rótulo `Mês` nas colunas A/E/I; o nome do mês está na coluna seguinte (B/F/J).
3. Despesas: da linha seguinte ao cabeçalho até a linha anterior a `Contas a pagar`. A descrição fica em B/F/J e o valor em D/H/L (é usado o valor calculado da célula, porque às vezes D é `=C`).
4. Receitas: linhas `Salário`, `Outros` e `Comissão`, com valor em C/G/K. Se C estiver vazio e D tiver número (fórmula sobrescrita), usa D e registra isso no relatório.
5. Guardado: valor de `Poupança` / `Para Poupança` em D/H/L, quando for > 0.
6. Meses depois do atual são ignorados (CA-09.6).

**Parcelas:** a descrição casa com `^(.*?)\s*\((\d+)/(\d+)\)$`. Os lançamentos são agrupados por
descrição normalizada (sem acento, minúscula, espaços colapsados) + N. Para cada grupo é criado um
parcelamento com `primeira = competência − (k−1)`. Parcelas encontradas viram parcelas do
parcelamento. Parcelas **futuras** (depois do mês atual) são geradas. Parcelas **passadas** que não
estão na planilha **não** são criadas, para não alterar totais históricos. Números repetidos ou fora
de sequência vão para o relatório (CA-09.2).

**Idempotência (CA-09.7):** cada lançamento importado guarda `chave_importacao = aba!célula`; reimportar atualiza em vez de duplicar.

**Relatório:** por mês, lista os totais calculados, as divergências em relação ao total digitado na planilha, as linhas incompletas, as parcelas suspeitas e os meses ignorados. É gravado em `storage/app/importacao/<data>.md` e impresso no terminal.

## 5. Acesso e segurança (RF-18, RNF-06)

- Autenticação padrão do Laravel com **um único usuário**. A tela pede só a senha; o e-mail do usuário fica fixo internamente.
- A senha inicial é definida com `php artisan financas:definir-senha` (mínimo de 6 caracteres, digitada duas vezes; o mesmo comando redefine a senha se ela for esquecida). Não há tela de "primeiro acesso", para que ninguém na rede consiga definir a senha antes do dono.
- Trocar a senha, pelo comando ou pela tela, gera um novo *remember token*: os aparelhos marcados para lembrar precisam entrar de novo.
- "Lembrar neste aparelho" usa o *remember token* do Laravel com duração de 30 dias. Sem ele, a sessão expira ao fechar o navegador (CA-18.4).
- `RateLimiter`: 5 tentativas por minuto por IP (CA-18.5).
- O servidor escuta em `0.0.0.0:${APP_PORT}` (padrão **8765**), acessível pelo IP do computador na rede de casa (ex.: `http://192.168.0.10:8765`).
- **HTTPS local (opcional, v1.2):** o FrankenPHP pode emitir um certificado da sua própria autoridade certificadora local. É preciso instalar o certificado raiz no celular uma vez. A instalação automática da CA no sistema do computador fica **desligada** (`skip_install_trust`), por causa do RNF-09. Isso criptografa a senha no Wi-Fi e é o que permite instalar o app como PWA (ver 7).

## 6. Execução

```
docker compose up -d        # app em http://<ip-do-computador>:8765
```

### 6.1 Isolamento (RNF-09)

A máquina roda outros ambientes (portas 80, 443, 2222, 3306, 3307, 5672, 8080… já ocupadas). Por isso:

- O Compose declara `name: financas`: containers, rede e imagem levam o prefixo `financas` e não se misturam com os de outros projetos.
- A única porta exposta é `APP_PORT` (padrão 8765), definida no `.env`. Ela foi verificada como livre.
- Não há volumes nomeados: dados, banco e backups ficam em pastas dentro do projeto.
- Os containers rodam com o UID/GID do usuário, para não criar arquivos de root no projeto.
- PHP, Composer e Node existem **só dentro da imagem** do projeto; a máquina não é alterada.
- Comandos de manutenção usam apenas `docker compose` dentro desta pasta, nunca comandos globais como `docker system prune`.

| Serviço | Função |
|---|---|
| `app` | FrankenPHP servindo o Laravel |
| `scheduler` | `php artisan schedule:work` — backup diário (RNF-08) |
| `node` | perfil `tools`, sob demanda: `docker compose run --rm node npm run build` |

O banco fica em `database/financas.sqlite` e os backups em `./backups`, ambos montados como volume e fora do git. O backup usa `VACUUM INTO` (cópia consistente mesmo com o app rodando) e mantém os 30 mais recentes.

## 7. Telas

| Tela | Conteúdo | Requisitos |
|---|---|---|
| Senha | campo de senha + "lembrar neste aparelho" | RF-18 |
| Início | total guardado em destaque, resumo do mês atual, "vence em breve", alertas, botão **+** (despesa / receita / compra no cartão) | CA-06.7, RF-13, RF-14, CA-16.3 |
| Mês | navegação ‹ mês › ; resumo; lista de despesas (filtro por status), receitas, guardado; total por categoria; selo "projeção" | RF-01..06, RF-08, RF-10, RF-11 |
| Ano | 12 meses × totais + totais anuais; toque abre o mês | RF-07 |
| Recorrências | lista, criar, alterar valor a partir de um mês, encerrar | RF-04 |
| Parcelamentos | lista com progresso (k/N), criar, alterar, cancelar | RF-05 |
| Cartões e faturas | cartões; faturas por mês com as compras; pagar fatura | RF-15 |
| Categorias | criar, renomear, excluir com reatribuição | RF-11 |
| Gráficos | mensal do ano, total guardado, por categoria | RF-12 |
| Configurações | trocar senha, guardado inicial, horizonte de vencimentos, exportar, backup e restauração | RF-17, RF-18, CA-06.6, CA-13.5 |

No celular, a navegação é uma barra inferior (Início, Mês, Ano, Mais). A ação **+** fica sempre visível.

**PWA (RF-16):** manifesto e ícones permitem "adicionar à tela inicial". O navegador só registra o *service worker*, e portanto só deixa instalar como app de verdade, em conexão segura (HTTPS ou `localhost`). Pelo IP da rede em HTTP, o celular cria um atalho que abre no navegador. Para cumprir o CA-16.2 por completo, a v1.2 inclui HTTPS local (seção 5).

## 8. Estratégia de testes

| Nível | O que cobre | Onde |
|---|---|---|
| Unitário de domínio | Todas as RD-xx e RV-xx, inclusive bordas: virada de ano, dia 31, resto de centavos, fechamento no dia | `tests/Unit/Domain` |
| Feature (Laravel + SQLite em memória) | Casos de uso e telas Livewire; um teste por CA | `tests/Feature` |
| Importação | Planilha sintética no layout legado, montada em código pelo próprio teste | `tests/Feature/Importacao` |
| Conferência com dados reais | Importa a planilha real e confere os totais mês a mês; é pulado se o arquivo não existir | `tests/Feature/Importacao/PlanilhaRealTest.php` + `tests/Fixtures/private/` *(fora do git)* |
| Rastreabilidade | Lê os CA-xx de `01-requirements.md` das entregas marcadas como concluídas e falha se algum não tiver teste com o ID no nome | `tests/Spec/RastreabilidadeTest.php` |

Convenção de nome: `it('CA-04.2 altera o valor só no mês escolhido', …)`.
