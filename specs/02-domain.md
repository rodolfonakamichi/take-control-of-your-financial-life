# 02 — Modelo de domínio e regras de cálculo

**Status:** Aprovado em 27/11/2026
**Origem:** engenharia reversa da planilha pessoal de gastos (abas `2025`, `2026`, `2027`).

## 1. Como a planilha funciona hoje

Uma aba por ano. Cada aba tem 12 blocos de mês (3 por "linha" de blocos), e cada bloco tem:

| Seção da planilha | Conteúdo | Cálculo |
|---|---|---|
| `Financeiro` (linhas 4–20 do bloco) | Lista de despesas: descrição (col. B) e valor (col. D) | digitado |
| `Contas a pagar` | Total das despesas do mês | `=SUM(D4:D20)` |
| `Recebidos` | Salário, Outros, Comissão (col. C) | `=C22+C23+C24` |
| `Poupança` / `Para Poupança` | Quanto vai ser guardado no mês | digitado |
| `Na poupança` | Saldo acumulado guardado | `=<Na poupança do mês anterior> + <Para Poupança>` |
| `Restante` | O que sobra no mês | `=Recebidos − Contas a pagar − Para Poupança` |

Comportamentos que hoje são **manuais** e que o sistema deve automatizar:

- **Despesas recorrentes** (aluguel, internet, academia, streaming, telefone…) são redigitadas todo mês.
- **Parcelamentos** são controlados no texto da descrição: `Viagem(2/6)`, `Curso (3/10)` — o número da parcela é atualizado à mão.
- **Faturas de cartão** (ex.: cartão de loja) entram como uma despesa por mês com valor variável.
- **Ano seguinte** é criado copiando a aba do ano anterior — por isso a aba futura nasce com valores que não são reais.
- O bloco de "Na poupança" não encadeia entre anos, e nos primeiros meses ele nem existe.

## 2. Entidades

### 2.1 Competência (Mês)
Par `(ano, mês)`. É a unidade de organização de tudo — equivale a um bloco da planilha.
Um lançamento pertence a uma competência, não a uma data. A data só aparece como **vencimento**
opcional de despesas (2.7) e como **data da compra** no cartão (2.9).

### 2.2 Lançamento
Unidade básica. Um valor que entra ou sai numa competência.

| Campo | Tipo | Observação |
|---|---|---|
| `tipo` | `despesa` \| `receita` | |
| `descricao` | texto | ex.: "Aluguel" |
| `valor` | dinheiro (centavos, ≥ 0) | sempre positivo; o sinal vem do `tipo` |
| `competencia` | (ano, mês) | |
| `categoria_receita` | `salario` \| `outros` \| `comissao` | só para receitas — espelha as 3 linhas de "Recebidos" |
| `origem` | `avulso` \| `recorrencia` \| `parcelamento` | de onde o lançamento veio |
| `origem_id` | referência | recorrência ou parcelamento que gerou o lançamento |
| `parcela_numero` | inteiro | só quando `origem = parcelamento` |

### 2.3 Recorrência (despesa fixa)
Molde de uma despesa que se repete todo mês.

| Campo | Observação |
|---|---|
| `descricao` | ex.: "Internet" |
| `valor_padrao` | valor sugerido a cada mês |
| `inicio` | competência da primeira ocorrência |
| `fim` | competência da última (opcional — vazio = sem fim) |

Ao gerar o lançamento de um mês, o valor pode ser **editado só naquele mês** (ex.: conta de
telefone que variou) sem alterar o padrão. Alterar o `valor_padrao` vale **daqui para frente**.

### 2.4 Parcelamento (compra parcelada / dívida)
| Campo | Observação |
|---|---|
| `descricao` | ex.: "Viagem" |
| `valor_parcela` | valor de cada parcela |
| `total_parcelas` | N |
| `primeira_competencia` | mês da parcela 1 |

Gera N lançamentos, um por mês consecutivo, exibidos como `Descrição (k/N)`.

### 2.5 Guardado (poupança e investimentos)
Um total único do que está guardado, sem separar por aplicação.

**Movimento do guardado**

| Campo | Observação |
|---|---|
| `competencia` | (ano, mês) |
| `tipo` | `guardar` \| `retirar` |
| `valor` | centavos, > 0 |
| `descricao` | opcional |

Pode haver vários movimentos no mesmo mês. Configuração: `guardado_inicial`, o total que já existia antes do primeiro mês controlado.

### 2.6 Categoria *(v1.1)*
| Campo | Observação |
|---|---|
| `nome` | único; lista inicial sugerida em CA-11.1 |

Lançamento, Recorrência, Parcelamento e Compra no cartão ganham `categoria` (opcional — vazio = "Sem categoria").

### 2.7 Status e vencimento *(v1.1)*
Campos adicionados a toda **despesa**:

| Campo | Observação |
|---|---|
| `status` | `a_pagar` \| `pago` |
| `pago_em` | data em que foi marcado como pago |
| `vencimento` | data (opcional). Em recorrências e parcelamentos guarda-se o **dia** (1–31) e a data é resolvida por mês (RD-11) |

### 2.8 Cartão *(v1.2)*
| Campo | Observação |
|---|---|
| `nome` | ex.: "Nubank", "Cartão loja" |
| `dia_fechamento` | 1–31 |
| `dia_vencimento` | 1–31 |

### 2.9 Compra no cartão *(v1.2)*
| Campo | Observação |
|---|---|
| `cartao` | |
| `data_compra` | data |
| `descricao`, `categoria` | |
| `valor_total` | centavos |
| `parcelas` | N ≥ 1 |

### 2.10 Fatura *(v1.2)*
Agrupamento **derivado**, não digitado: todas as parcelas de compras de um cartão que caem num mesmo
fechamento. Identificada por `(cartao, mês de fechamento)`. Tem `status` próprio (paga / a pagar).
No resumo do mês ela entra como uma única despesa, no mês do seu vencimento (RD-15).

## 3. Regras de cálculo

Todas as regras operam sobre **uma competência** `m`.

| ID | Regra | Fórmula no sistema | Origem na planilha |
|---|---|---|---|
| **RD-01** | Total de despesas | `ContasAPagar(m) = Σ valor dos lançamentos tipo despesa em m` | `Contas a pagar = SUM(D4:D20)` |
| **RD-02** | Total recebido | `Recebidos(m) = Σ valor dos lançamentos tipo receita em m` (= Salário + Outros + Comissão) | `Recebidos = C22+C23+C24` |
| **RD-03** | Guardado no mês | `GuardadoNoMes(m) = Σ guardar em m − Σ retirar em m` (pode ser negativo) | `Para Poupança` |
| **RD-04** | Restante | `Restante(m) = Recebidos(m) − ContasAPagar(m) − GuardadoNoMes(m)`. Só do mês: **não** acumula para o seguinte. | `Restante = D22-D21-D25` |
| **RD-05** | Total guardado | `TotalGuardado(m) = TotalGuardado(m−1) + GuardadoNoMes(m)`; antes do primeiro mês com movimento, `guardado_inicial`. **Encadeia entre anos** (dezembro → janeiro). | `Na poupança = L75+D99` (hoje quebra na virada do ano) |
| **RD-06** | Parcela k | A parcela `k` (1 ≤ k ≤ N) de um parcelamento cai em `primeira_competencia + (k−1) meses` | numeração manual `(k/N)` |
| **RD-07** | Recorrência no mês | Uma recorrência gera exatamente **um** lançamento em cada competência `m` com `inicio ≤ m ≤ fim` | redigitação manual |
| **RD-08** | Totais anuais | `TotalAno(campo) = Σ campo(m)` para os 12 meses do ano (despesas, receitas, aportes, restante) | não existe hoje |
| **RD-09** | Pago e a pagar | `JaPago(m) = Σ despesas de m com status pago`; `FaltaPagar(m) = ContasAPagar(m) − JaPago(m)`. Não altera RD-01 nem RD-04. | não existe hoje |
| **RD-10** | Total por categoria | `TotalCategoria(m, c) = Σ despesas de m na categoria c`. Faturas de cartão são **abertas** nas compras que as compõem; a linha "Fatura" não conta como categoria. `Σ TotalCategoria(m, c)` para todo c (incluindo "Sem categoria") = `ContasAPagar(m)`. | não existe hoje |
| **RD-11** | Dia inexistente no mês | Um dia `d` num mês com menos de `d` dias é resolvido para o último dia do mês (vale para vencimento, fechamento e vencimento de cartão). | — |
| **RD-12** | Fatura de uma compra | Seja `F` o dia de fechamento resolvido (RD-11) no mês da compra. Se `dia(data_compra) ≤ F`, a compra pertence à fatura que fecha **nesse mês**; senão, à que fecha **no mês seguinte**. | — |
| **RD-13** | Vencimento da fatura | Para uma fatura que fecha no mês `M`: se `dia_vencimento > dia_fechamento`, vence em `M`; senão vence em `M+1`. O dia é resolvido por RD-11. | — |
| **RD-14** | Parcelas no cartão | A parcela `k` de uma compra em N× cai na fatura `fatura_da_compra + (k−1)`. Valor: `floor(total / N)` centavos para cada parcela, e a primeira recebe também o resto `total − N·floor(total/N)`. | — |
| **RD-16** | Alteração de parcelamento | Alterar o valor ou cancelar um parcelamento afeta só as parcelas com `competência ≥ mês atual` **e** status `a_pagar`. As demais ficam como estão. Vale também para compras no cartão. | — |
| **RD-15** | Fatura no resumo do mês | A fatura entra como **uma** despesa na competência do seu vencimento (RD-13), com valor = Σ parcelas que a compõem. Pago/a pagar é da fatura inteira. | Hoje: uma linha do cartão de loja por mês, com valor digitado |

### Decisões em aberto sobre as regras

- ~~Restante acumula?~~ **Decidido:** não acumula; o acumulado que importa é o `Total guardado` (RD-04, RD-05).
- ~~Retirada da poupança?~~ **Decidido:** sim, por movimento de `retirar` (RD-03, RV-07).
- **Salário quebrado em partes.** Resolvido sem regra nova: lançam-se várias receitas no mês (CA-02.2).

## 4. Regras de validação (derivadas de erros encontrados na planilha)

| ID | Regra | Por quê |
|---|---|---|
| **RV-01** | Um lançamento precisa de descrição e valor > 0 | Há linhas na planilha com valor sem descrição e descrição sem valor — e o valor faltante some do total. |
| **RV-02** | Totais são **sempre** calculados, nunca digitados | Em vários meses a fórmula de `Recebidos` foi sobrescrita por um número fixo, e num deles o número não bate com as receitas lançadas. |
| **RV-03** | Numeração de parcela é gerada pelo sistema | Há parcela repetida (mesmo `k/N` em dois meses) e parcela sem numeração. |
| **RV-04** | Saldo da poupança é derivado, nunca referenciado à mão | A fórmula de `Na poupança` aponta para célula errada/vazia em alguns blocos. |
| **RV-05** | Alertar mês sem receita | Há mês com `Recebidos = 0` e `Restante` negativo por esquecimento de lançar o salário. |
| **RV-06** | Descrições de recorrências são padronizadas | Mesma conta grafada de formas diferentes ao longo do ano dificulta qualquer análise. |
| **RV-07** | `TotalGuardado(m) ≥ 0` para todo mês `m` | Uma retirada (ou a exclusão de um guardar antigo) não pode deixar o total negativo em nenhum mês daquele em diante. |
