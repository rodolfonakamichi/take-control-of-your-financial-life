# Specs — Take control of your financial life

Este projeto é desenvolvido de forma **spec-driven**: nenhuma linha de código é escrita
sem um requisito aprovado que a justifique, e todo requisito tem critérios de aceite
que viram testes automatizados.

## Documentos

| Arquivo | Conteúdo | Status |
|---|---|---|
| [01-requirements.md](01-requirements.md) | O que o sistema faz — requisitos com critérios de aceite (Dado/Quando/Então) | Aprovado |
| [02-domain.md](02-domain.md) | Entidades e regras de cálculo, rastreadas até as fórmulas da planilha original | Aprovado |
| [03-design.md](03-design.md) | Arquitetura, stack, modelo de dados, telas | Aprovado |
| [04-tasks.md](04-tasks.md) | Plano de implementação em tarefas pequenas, cada uma ligada a requisitos | Aprovado |

## Fluxo

1. **Especificar** — requisitos e regras de domínio são escritos e revisados.
2. **Aprovar** — o dono do projeto aprova (status do documento passa para `Aprovado`).
3. **Projetar** — design técnico derivado dos requisitos aprovados.
4. **Planejar** — tarefas com rastreabilidade `Tarefa → Requisito → Critério de aceite`.
5. **Implementar** — cada critério de aceite vira um teste; a tarefa só fecha com os testes passando.
6. **Mudou a regra?** — altera-se primeiro a spec, depois o teste, depois o código.

## Convenções

- Requisitos: `RF-xx` (funcional), `RNF-xx` (não funcional).
- Regras de domínio: `RD-xx`. Cada regra cita a fórmula da planilha que a originou, quando houver.
- Validações: `RV-xx`.
- Itens marcados com **[A CONFIRMAR]** são decisões em aberto e bloqueiam a aprovação.
- A planilha original (`*.xlsx`) **não** é versionada — contém dados pessoais.

## Registro de decisões

| Data | Decisão |
|---|---|
| 27/11/2026 | Todos os candidatos (RF-10 a RF-17) entram na v1, divididos nas entregas v1.0 / v1.1 / v1.2. |
| 27/11/2026 | Acesso apenas pela rede de casa, sem hospedagem (protegido pela senha única abaixo). |
| 27/11/2026 | Lembretes de vencimento apenas dentro do app. |
| 27/11/2026 | Categorias criadas pelo usuário, partindo de uma lista sugerida. |
| 27/11/2026 | Compras no cartão caem na fatura pela data da compra e pelo dia de fechamento do cartão. |
| 27/11/2026 | Senha única de acesso (sem usuários), definida por comando. |
| 27/11/2026 | O restante é só do mês; o acumulado mostrado é o total guardado (poupança e investimentos). |
| 27/11/2026 | Pode guardar e retirar do guardado; o total nunca fica negativo. |
| 27/11/2026 | Importação só até o mês atual. |
| 27/11/2026 | Alterar ou cancelar parcelamento não mexe em parcelas passadas nem pagas. |
| 27/11/2026 | Stack: Laravel + Livewire + SQLite, rodando em Docker. |
| 27/11/2026 | Isolamento dos outros projetos da máquina (RNF-09); porta padrão 8765, já que a 8080 está ocupada. |
| 27/11/2026 | Specs aprovadas; início da implementação. |
