# 04 — Plano de implementação

**Status:** Aprovado em 27/11/2026
**Deriva de:** [01-requirements.md](01-requirements.md), [02-domain.md](02-domain.md), [03-design.md](03-design.md)

Cada tarefa só fecha quando os testes dos CAs listados existem e passam. A ordem respeita as
dependências; tarefas de domínio (puras) vêm antes das telas que as usam.

Legenda: ☐ a fazer · ◐ em andamento · ☑ concluída

## v1.0 — Paridade com a planilha

### Fundação
| ID | Tarefa | Cobre | Pronto quando |
|---|---|---|---|
| ☑ T01 | Scaffold: Laravel + Livewire + Tailwind + Pest em Docker Compose (FrankenPHP, SQLite, serviço `node`); `README` com como rodar | RNF-05, RNF-07, RNF-09 | `docker compose up` abre a página inicial na porta 8765; `pest` roda; banco e `.env` fora do git; nenhum container ou porta de outro projeto alterado |
| ☑ T02 | Teste de rastreabilidade CA → teste | RNF-04 | Falha listando os CAs sem teste das entregas marcadas como concluídas |
| ☑ T03 | `Dinheiro` e `Competencia` | RNF-01, RNF-02, RD-11 | Aritmética, divisão com resto, formatação BRL, virada de ano e último dia do mês testados |

### Domínio
| ID | Tarefa | Cobre | Pronto quando |
|---|---|---|---|
| ☑ T04 | `CalculadoraResumo` | RD-01..04, RD-08 · CA-02.1, CA-02.2, CA-03.1, CA-03.4, CA-06.4 | Testes unitários verdes |
| ☑ T05 | `LivroGuardado` | RD-03, RD-05, RV-07 · CA-06.2, CA-06.3, CA-06.5 | Inclui retirada que deixaria um mês futuro negativo |
| ☑ T06 | `ResolvedorRecorrencia` (valores vigentes + exceções) | RD-07 · CA-04.1..04.5 | Inclui mudança de valor a partir de um mês e encerramento |
| ☑ T07 | `GeradorParcelas` | RD-06, RD-16 · CA-05.1..05.4 | Inclui parcelas atravessando o ano e alteração que não toca passado nem pagas |

### Persistência e telas
| ID | Tarefa | Cobre | Pronto quando |
|---|---|---|---|
| ☐ T08 | Migrations e models da v1.0; caso de uso `MontarMes` | Design §2.2, §3 | Teste de feature monta um mês com avulsos, recorrências, parcelas e guardado |
| ☐ T09 | Senha única: login, lembrar aparelho, limite de tentativas, sair, trocar senha, comando `financas:definir-senha` | RF-18 · CA-18.1..18.6 | Testes de feature verdes |
| ☐ T10 | Layout base mobile-first: barra inferior, botão **+**, navegação por mês e ano | RNF-07 · CA-16.4 (parcial) | Telas sem rolagem horizontal em 360px |
| ☐ T11 | Tela do mês: lançar, editar e excluir despesas e receitas; resumo | RF-01, RF-02, RF-03 · CA-01.x, CA-02.x, CA-03.x | Testes Livewire verdes |
| ☐ T12 | Tela de recorrências | RF-04 | CA-04.x via tela |
| ☐ T13 | Tela de parcelamentos | RF-05 · CA-05.x | Incluindo exclusão total com confirmação (CA-05.5) |
| ☐ T14 | Guardado: movimentos no mês, guardado inicial nas configurações | RF-06 · CA-06.1, CA-06.6 | Testes verdes |
| ☐ T15 | Visão anual | RF-07 · CA-07.x | Testes verdes |
| ☐ T16 | Projeção de meses futuros (selo, navegação livre de ano) | RF-08 · CA-08.x | Mês de um ano nunca aberto mostra recorrências e parcelas |
| ☐ T17 | Tela inicial v1.0: total guardado, resumo do mês atual | CA-06.7 | Teste verde |
| ☐ T18 | Importador da planilha legada + relatório | RF-09 · CA-09.x | Teste com planilha sintética verde; reimportação não duplica |
| ☐ T19 | Backup diário com retenção de 30 | RNF-08 | Teste do comando; agendado no `scheduler` |
| ☐ G1 | **Portão v1.0:** importar a planilha real, revisar o relatório com o dono, corrigir as inconsistências encontradas, conferir os totais | — | Dono aprova os números; entrega marcada como concluída |

## v1.1 — Controle do mês

| ID | Tarefa | Cobre | Pronto quando |
|---|---|---|---|
| ☐ T20 | Status pago / a pagar; filtro; já pago × falta pagar | RF-10 · RD-09 · CA-10.1..10.3, CA-10.5 | Testes verdes (CA-10.4 fica para T26) |
| ☐ T21 | Categorias: lista inicial, CRUD, reatribuição ao excluir, herança, sugestão por descrição, total por categoria | RF-11 · RD-10 · CA-11.x | Testes verdes; soma das categorias = contas a pagar |
| ☐ T22 | Vencimentos: dia nas recorrências e parcelamentos, data nos avulsos; painel "vence em breve" e vencidas; horizonte configurável | RF-13 · RD-11 · CA-13.1, CA-13.3..13.6 | Testes verdes (CA-13.2 fica para T26) |
| ☐ T23 | `DetectorAlertas` + exibição no início e no mês | RF-14 · RV-05 · CA-14.x | Testes verdes |
| ☐ G2 | **Portão v1.1:** uso real por um mês inteiro | — | Dono aprova |

## v1.2 — Cartão, análise e mobilidade

| ID | Tarefa | Cobre | Pronto quando |
|---|---|---|---|
| ☐ T24 | `CalendarioFatura` e `MontadorFatura` | RD-11..15 · CA-15.3, CA-15.4, CA-15.5, CA-15.9 | Testes unitários com fechamento no dia, dia 31 e vencimento antes do fechamento |
| ☐ T25 | Cartões e compras: cadastro, compra parcelada, alteração e cancelamento (RD-16) | RF-15 · CA-15.1, CA-15.2 | Testes verdes |
| ☐ T26 | Faturas: no resumo do mês do vencimento, detalhe com compras, pagar fatura, categorias abertas, projeção | CA-15.6..15.8, CA-10.4, CA-13.2 | Testes verdes |
| ☐ T27 | Gráficos | RF-12 · CA-12.x | Números dos gráficos vêm das mesmas classes de domínio |
| ☐ T28 | PWA: manifesto, ícones, *service worker*; HTTPS local com o FrankenPHP; guia de instalação no celular | RF-16 · CA-16.x · RNF-06 | Instalado num celular real pela rede de casa |
| ☐ T29 | Exportação CSV e `.xlsx`; backup completo e restauração | RF-17 · CA-17.x | Restaurar um backup reproduz os mesmos totais |
| ☐ G3 | **Portão v1.2** | — | Dono aprova |
