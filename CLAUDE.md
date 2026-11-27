# Take control of your financial life

Controle financeiro pessoal (substitui uma planilha anual de gastos). Laravel 13 + Livewire + Tailwind + SQLite, testes em Pest.

## Spec-driven — leia antes de codar

- As specs em `specs/` são a fonte da verdade: `01-requirements.md` (RF/CA), `02-domain.md` (RD/RV), `03-design.md`, `04-tasks.md`.
- Nenhum código sem requisito aprovado. Mudou a regra? Primeiro a spec, depois o teste, depois o código.
- Todo critério de aceite `CA-xx.y` tem teste com o ID no nome: `it('CA-04.2 altera o valor só no mês escolhido', …)`.
- Trabalhe seguindo `specs/04-tasks.md` e marque a tarefa (☐ → ☑) ao concluir.

## Isolamento (RNF-09) — obrigatório

A máquina roda outros projetos e containers. **Nunca** instale ou atualize PHP, Composer, Node, certificados ou `/etc/hosts` no host.
Tudo roda nos containers deste projeto (`name: financas` no Compose). Porta exposta só a `APP_PORT` (padrão 8765).
Nunca use comandos Docker globais (`docker system prune`, `docker rm`/`stop` em containers de outros projetos).

## Comandos

```sh
docker compose up -d --build                       # sobe app (http://localhost:8765) e scheduler
docker compose run --rm app composer <args>        # composer
docker compose run --rm app php artisan <args>     # artisan
docker compose run --rm app ./vendor/bin/pest      # testes
docker compose run --rm node npm run build         # assets (Vite/Tailwind)
```

## Arquitetura

- `app/Domain`: regras de cálculo em PHP puro, sem Laravel. Todo cálculo mora aqui.
- `app/Application`: casos de uso (orquestram, não calculam).
- `app/Infra`: repositórios (model ↔ domínio), PhpSpreadsheet, backup, relógio (`Relogio`; nos testes use `RelogioFixo`).
- `app/Models`: models Eloquent. O domínio nunca recebe um model.
- `app/Livewire` + `resources/views`: interface mobile-first.
- Dinheiro sempre em centavos (`int`). Competência como `AAAA-MM`. Totais nunca são gravados no banco.
- Código e interface em português do Brasil.

## Dados pessoais

Planilha original, banco (`database/*.sqlite`), backups e `tests/Fixtures/private/` ficam fora do git.
