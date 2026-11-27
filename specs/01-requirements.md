# 01 — Requisitos

**Status:** Aprovado em 27/11/2026
**Regras de cálculo referenciadas:** ver [02-domain.md](02-domain.md).

## Objetivo

Substituir a planilha anual de gastos por uma aplicação que aplique as mesmas regras de cálculo,
elimine o trabalho manual (recorrências, parcelas, cópia de ano) e torne impossíveis os erros
de fórmula sobrescrita ou referência quebrada — e que vá além da planilha com controle de
pagamento, categorias, vencimentos, faturas de cartão, gráficos e uso pelo celular.

## Escopo e entregas

Todos os requisitos abaixo fazem parte da v1. Para ter algo usável cedo, a v1 sai em três entregas;
cada entrega só começa quando a anterior está com todos os testes passando.

| Entrega | Requisitos | Resultado |
|---|---|---|
| **v1.0 — Paridade com a planilha** | RF-01 a RF-09, RF-18 | A planilha pode ser aposentada: histórico importado, meses calculados, recorrências e parcelas automáticas |
| **v1.1 — Controle do mês** | RF-10, RF-11, RF-13, RF-14 | Saber o que já foi pago, o que vence, para onde vai o dinheiro e o que está errado |
| **v1.2 — Cartão, análise e mobilidade** | RF-15, RF-12, RF-16, RF-17 | Faturas detalhadas, gráficos, app instalável no celular e exportação |

O layout é *mobile-first* desde a v1.0 (RNF-07); o RF-16 acrescenta a instalação como app e o acesso pela rede de casa.

## Fora de escopo (v1)

- Acesso fora da rede de casa, hospedagem e contas de usuário (há só uma senha única — RF-18).
- Lembretes fora do app (push, e-mail, Telegram).
- Integração com bancos / importação de extrato OFX.
- Múltiplos usuários.
- Separar o guardado por aplicação (poupança, CDB, ações…) — na v1 é um total único.

---

## v1.0 — Paridade com a planilha

### RF-01 — Lançar despesa no mês
Como usuário, quero registrar uma despesa numa competência para controlar o que tenho a pagar.

- **CA-01.1** Dado o mês 10/2025, quando lanço "Aluguel" de R$ 1.200,00, então ele aparece na lista de despesas de 10/2025 e `Contas a pagar` aumenta R$ 1.200,00.
- **CA-01.2** Dado um lançamento sem descrição ou com valor ≤ 0, quando tento salvar, então o sistema recusa e informa o motivo (RV-01).
- **CA-01.3** Dado um lançamento existente, quando edito o valor ou o excluo, então `Contas a pagar` e `Restante` do mês são recalculados.

### RF-02 — Lançar receita no mês
Como usuário, quero registrar o que recebi (salário, outros, comissão).

- **CA-02.1** Dado o mês 10/2025, quando lanço Salário R$ 4.000,00 e Comissão R$ 200,00, então `Recebidos` = R$ 4.200,00 (RD-02).
- **CA-02.2** É possível lançar mais de uma receita da mesma categoria no mesmo mês, e todas somam.

### RF-03 — Resumo do mês
Como usuário, quero ver os totais do mês como vejo hoje no rodapé de cada bloco da planilha.

- **CA-03.1** Dado um mês com despesas de R$ 2.800,00, receitas de R$ 4.000,00 e R$ 200,00 guardados, então o resumo mostra `Contas a pagar` R$ 2.800,00, `Recebidos` R$ 4.000,00, `Guardado no mês` R$ 200,00 e `Restante` R$ 1.000,00 (RD-01..RD-04).
- **CA-03.2** Os totais são somente leitura — não há como digitar um total manualmente (RV-02).
- **CA-03.3** `Restante` negativo é destacado visualmente.
- **CA-03.4** O `Restante` é só do mês: ele **não** passa para o mês seguinte.
- **CA-03.5** O resumo do mês mostra também o `Total guardado` até aquele mês (RD-05).

### RF-04 — Despesas recorrentes
Como usuário, quero cadastrar uma despesa fixa uma vez e vê-la em todos os meses.

- **CA-04.1** Dada a recorrência "Internet" R$ 50,00 a partir de 01/2026 sem fim, quando abro qualquer mês ≥ 01/2026, então "Internet" R$ 50,00 está na lista (RD-07).
- **CA-04.2** Dado o mês 03/2026, quando altero o valor de "Internet" só nesse mês para R$ 60,00, então 03/2026 mostra R$ 60,00 e os demais meses continuam R$ 50,00.
- **CA-04.3** Quando altero o valor padrão da recorrência a partir de 06/2026, então meses ≥ 06/2026 usam o novo valor e meses anteriores não mudam.
- **CA-04.4** Quando encerro a recorrência em 08/2026, então ela não aparece em meses ≥ 09/2026.
- **CA-04.5** Uma recorrência nunca gera dois lançamentos no mesmo mês.

### RF-05 — Compras parceladas
Como usuário, quero cadastrar uma compra parcelada uma vez e ter as parcelas distribuídas automaticamente.

- **CA-05.1** Dado o parcelamento "Viagem", 6× R$ 250,00, primeira parcela em 10/2025, então existem lançamentos "Viagem (1/6)" em 10/2025 … "Viagem (6/6)" em 03/2026 (RD-06), atravessando a virada de ano.
- **CA-05.2** A numeração `k/N` é gerada pelo sistema e não é editável (RV-03).
- **CA-05.3** Quando altero o valor da parcela, só mudam as parcelas do mês atual em diante que ainda não foram pagas; parcelas de meses passados e parcelas pagas mantêm o valor (RD-16).
- **CA-05.4** Quando cancelo um parcelamento, somem as parcelas do mês atual em diante que ainda não foram pagas; parcelas de meses passados e parcelas pagas permanecem (RD-16).
- **CA-05.5** Um parcelamento só pode ser excluído por completo (inclusive o passado) com confirmação explícita.

### RF-06 — Guardado (poupança e investimentos)
Como usuário, quero registrar quanto guardo ou retiro por mês e ver o total que tenho guardado.

- **CA-06.1** Num mês posso lançar movimentos de **guardar** e de **retirar**, cada um com valor e descrição opcional (ex.: "CDB", "resgate para viagem").
- **CA-06.2** Dado total guardado inicial R$ 0,00 e movimentos de +R$ 100 em jan, nada em fev e +R$ 250 em mar, então o total guardado em mar é R$ 350,00 (RD-05).
- **CA-06.3** Dado total guardado de R$ 1.000,00 em 12/2025 e +R$ 100,00 em 01/2026, então o total em 01/2026 é R$ 1.100,00 (encadeia entre anos).
- **CA-06.4** Dado um mês com receitas de R$ 4.000,00, despesas de R$ 3.000,00 e uma retirada de R$ 500,00, então `Guardado no mês` = −R$ 500,00 e `Restante` = R$ 1.500,00: guardar diminui o restante e retirar aumenta (RD-03, RD-04).
- **CA-06.5** Uma retirada que deixaria o total guardado negativo em qualquer mês, daquele em diante, é recusada (RV-07).
- **CA-06.6** O total guardado inicial (o que já existia antes do primeiro mês controlado) é configurável.
- **CA-06.7** A tela inicial mostra o `Total guardado` atual em destaque.

### RF-07 — Visão anual
Como usuário, quero ver o ano inteiro de uma vez, como numa aba da planilha.

- **CA-07.1** A visão de um ano mostra os 12 meses com `Contas a pagar`, `Recebidos`, `Guardado no mês`, `Restante` e `Total guardado` de cada um.
- **CA-07.2** A visão mostra os totais anuais (RD-08).
- **CA-07.3** De qualquer mês da visão anual é possível abrir o detalhe do mês.

### RF-08 — Projeção de meses futuros
Como usuário, quero ver como ficarão os próximos meses sem copiar nada.

- **CA-08.1** Um mês futuro sem lançamentos avulsos mostra automaticamente as recorrências ativas, as parcelas e as faturas de cartão que caem nele.
- **CA-08.2** Meses futuros são marcados como "projeção" até que o mês chegue.
- **CA-08.3** Não existe "criar ano" — qualquer ano pode ser navegado e é montado a partir das regras.

### RF-09 — Importar a planilha atual
Como usuário, quero trazer meu histórico da planilha para não começar do zero.

- **CA-09.1** Dado o `.xlsx` no layout atual (uma aba por ano, blocos de mês), quando importo, então cada linha da seção `Financeiro` vira uma despesa, cada linha de `Recebidos` com valor vira uma receita e `Para Poupança`/`Poupança` com valor vira um movimento de guardar, na competência correta.
- **CA-09.2** Descrições no formato `Texto (k/N)` são reconhecidas como parcelas: o importador cria o parcelamento com a 1ª parcela em `competência − (k−1)` e gera as parcelas restantes nos meses seguintes. Numerações conflitantes (mesmo `k/N` em dois meses) vão para o relatório.
- **CA-09.3** Após importar, os totais de cada mês conferem com os totais **calculados a partir dos lançamentos** da planilha; divergências com valores digitados à mão na planilha são listadas num relatório, sem bloquear a importação.
- **CA-09.4** Linhas incompletas (descrição sem valor ou valor sem descrição) são listadas no relatório para correção.
- **CA-09.5** Lançamentos importados entram sem categoria e com status "pago" quando a competência é anterior ao mês atual.
- **CA-09.6** Só são importados meses até o mês atual, inclusive. Meses futuros da planilha são ignorados e contados no relatório (a aba do ano seguinte é uma cópia, não dado real).
- **CA-09.7** A importação pode ser repetida: importar de novo a mesma planilha não duplica lançamentos.

### RF-18 — Senha de acesso
Como usuário, quero uma senha para que outras pessoas no meu Wi-Fi não abram minhas finanças.

- **CA-18.1** Toda página exige estar autenticado; sem sessão, o sistema mostra a tela de senha.
- **CA-18.2** Há uma única senha, sem nome de usuário. Ela é definida na instalação e pode ser trocada informando a senha atual.
- **CA-18.3** A senha é guardada só como hash; nunca em texto puro.
- **CA-18.4** Com "lembrar neste aparelho", a sessão dura 30 dias; sem isso, termina ao fechar o navegador.
- **CA-18.5** Depois de 5 tentativas erradas em 1 minuto, novas tentativas daquele aparelho ficam bloqueadas por 1 minuto.
- **CA-18.6** Há botão de sair.

---

## v1.1 — Controle do mês

### RF-10 — Pago / a pagar
Como usuário, quero marcar o que já paguei para saber quanto ainda falta pagar no mês.

- **CA-10.1** Toda despesa nasce com status "a pagar" e pode ser marcada como "paga" (e desmarcada).
- **CA-10.2** Dado um mês com `Contas a pagar` R$ 2.800,00 em que já marquei R$ 1.900,00 como pago, então o resumo mostra `Já pago` R$ 1.900,00 e `Falta pagar` R$ 900,00 (RD-09).
- **CA-10.3** Marcar como pago **não** altera `Contas a pagar` nem `Restante` — só divide o total entre pago e a pagar.
- **CA-10.4** Uma fatura de cartão é marcada como paga como um todo, e não compra por compra (RF-15).
- **CA-10.5** A lista do mês pode ser filtrada por status (todas / a pagar / pagas).

### RF-11 — Categorias
Como usuário, quero classificar as despesas para saber para onde vai meu dinheiro.

- **CA-11.1** O sistema já vem com uma lista inicial de categorias: Moradia, Contas da casa, Assinaturas, Transporte, Saúde e academia, Educação, Alimentação, Compras, Lazer e viagens, Dívidas e empréstimos, Outros.
- **CA-11.2** Posso criar, renomear e excluir categorias.
- **CA-11.3** Ao excluir uma categoria que tem lançamentos, o sistema pede para escolher outra categoria para recebê-los, ou deixá-los "Sem categoria".
- **CA-11.4** Recorrências, parcelamentos e compras no cartão têm categoria, e os lançamentos gerados por eles herdam essa categoria.
- **CA-11.5** Ao lançar uma despesa com a mesma descrição de uma já categorizada, o sistema sugere a mesma categoria.
- **CA-11.6** O detalhe do mês e a visão anual mostram o total por categoria (RD-10).

### RF-13 — Vencimentos
Como usuário, quero saber o que vence nos próximos dias para não atrasar contas.

- **CA-13.1** Despesas podem ter dia de vencimento. Recorrências têm um dia fixo (ex.: todo dia 10) que vale para todos os meses gerados; parcelamentos também.
- **CA-13.2** Faturas de cartão vencem no dia de vencimento do cartão (RD-13).
- **CA-13.3** A tela inicial tem um painel "Vence em breve" com as despesas **a pagar** que vencem nos próximos 7 dias, ordenadas por data, com total.
- **CA-13.4** O painel destaca separadamente as contas **vencidas** (data passou e status "a pagar").
- **CA-13.5** O horizonte de "próximos dias" é configurável (padrão: 7).
- **CA-13.6** Dia de vencimento inexistente no mês (ex.: 31 em fevereiro) vira o último dia do mês (RD-11).

### RF-14 — Alertas de consistência
Como usuário, quero ser avisado de situações que provavelmente são erro ou problema.

- **CA-14.1** Mês atual ou passado sem nenhuma receita lançada gera o alerta "Mês sem receita" (RV-05).
- **CA-14.2** Mês futuro com `Restante` projetado negativo gera o alerta "Restante previsto negativo", com o valor.
- **CA-14.3** Conta vencida e não paga gera alerta (mesma regra do CA-13.4).
- **CA-14.4** Os alertas aparecem na tela inicial e no mês a que se referem; cada alerta leva ao mês correspondente.
- **CA-14.5** Alertas são calculados, não armazenados: somem sozinhos quando a causa é corrigida.

---

## v1.2 — Cartão, análise e mobilidade

### RF-15 — Faturas de cartão
Como usuário, quero lançar as compras do cartão e deixar o sistema montar a fatura de cada mês.

- **CA-15.1** Posso cadastrar cartões com nome, dia de fechamento e dia de vencimento.
- **CA-15.2** Lanço uma compra com cartão, data, descrição, valor, categoria e número de parcelas (padrão 1).
- **CA-15.3** Dado um cartão com fechamento dia 5 e vencimento dia 12: uma compra em 03/03 entra na fatura que fecha em 05/03 e vence em 12/03; uma compra em 06/03 entra na fatura que fecha em 05/04 e vence em 12/04 (RD-12, RD-13).
- **CA-15.4** Compra no dia do fechamento entra na fatura que fecha naquele dia.
- **CA-15.5** Dada uma compra de R$ 600,00 em 3× na fatura de 04/2026, então R$ 200,00 entram em cada uma das faturas de 04, 05 e 06/2026, exibidas como "Descrição (k/3)" (RD-14).
- **CA-15.6** Cada fatura aparece no mês **do seu vencimento** como uma única despesa "Fatura <cartão>", com valor = soma das compras e parcelas da fatura, e entra em `Contas a pagar` (RD-15).
- **CA-15.7** Abrindo a fatura vejo as compras que a compõem, com categoria; o total por categoria do mês considera as compras da fatura, e não a fatura como um item só.
- **CA-15.8** Faturas futuras (com parcelas já lançadas) aparecem na projeção (RF-08).
- **CA-15.9** Valor de parcela que não divide exato: as parcelas são iguais e a diferença de centavos vai na primeira (RD-14).

### RF-12 — Gráficos
Como usuário, quero ver a evolução das minhas finanças sem montar nada.

- **CA-12.1** Gráfico mensal do ano: `Recebidos` × `Contas a pagar` por mês, com a linha de `Restante`.
- **CA-12.2** Gráfico da evolução do total guardado ao longo dos meses, atravessando anos.
- **CA-12.3** Gráfico de gastos por categoria, do mês selecionado e do ano.
- **CA-12.4** Meses de projeção aparecem visualmente diferentes dos meses realizados.
- **CA-12.5** Os números de todos os gráficos são os mesmos dos resumos (mesmas regras RD-xx, sem cálculo paralelo).

### RF-16 — Uso pelo celular
Como usuário, quero lançar gastos pelo celular na hora, em casa.

- **CA-16.1** O sistema é acessível pelo navegador do celular na **rede de casa** (Wi-Fi), pelo endereço do computador onde ele roda.
- **CA-16.2** Pode ser instalado na tela inicial do celular como app (PWA).
- **CA-16.3** Lançar uma despesa ou uma compra no cartão pelo celular leva no máximo 3 toques a partir da tela inicial, mais o preenchimento.
- **CA-16.4** Todas as telas funcionam numa largura de 360px sem rolagem horizontal.

### RF-17 — Exportar
Como usuário, quero tirar meus dados do sistema quando quiser.

- **CA-17.1** Exporto os lançamentos de um período (mês, ano ou tudo) em CSV e em `.xlsx`.
- **CA-17.2** O `.xlsx` de um ano tem uma aba de resumo (os 12 meses com os totais) e uma aba com os lançamentos.
- **CA-17.3** Exporto um backup completo (todos os dados) que pode ser restaurado no sistema.

---

## Requisitos não funcionais

- **RNF-01** Valores monetários armazenados como inteiro em centavos — nunca ponto flutuante.
- **RNF-02** Interface em português do Brasil; moeda exibida como `R$ 1.234,56`; datas como `dd/mm/aaaa`.
- **RNF-03** Todas as regras RD-xx implementadas numa camada de domínio isolada, sem dependência de framework, coberta por testes unitários.
- **RNF-04** Cada critério de aceite `CA-xx.y` tem ao menos um teste automatizado que o referencia pelo ID.
- **RNF-05** Dados pessoais não são versionados (planilha original e banco de dados fora do git).
- **RNF-06** O sistema roda no computador do usuário e é acessível apenas na rede local, protegido pela senha única do RF-18. Na rede de casa a conexão é HTTP, então a senha trafega sem criptografia dentro do Wi-Fi; HTTPS local é opcional (ver design).
- **RNF-07** Layout *mobile-first* desde a v1.0.
- **RNF-08** Backup automático diário do banco de dados para uma pasta local configurável, mantendo os últimos 30.
- **RNF-09** Isolamento: o projeto não pode afetar outros projetos da máquina. Roda só nos seus próprios containers, com nomes, rede e porta próprios (porta configurável). Nada é instalado ou atualizado no sistema (PHP, Composer, Node, certificados, `/etc/hosts`).
