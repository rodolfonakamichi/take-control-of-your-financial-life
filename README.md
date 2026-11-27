# Take control of your financial life

Controle financeiro pessoal que substitui a planilha anual de gastos: resumo do mês, recorrências e
parcelas automáticas, total guardado, projeção dos próximos meses e, nas próximas entregas,
pago / a pagar, categorias, vencimentos, faturas de cartão, gráficos e uso pelo celular.

O projeto é **spec-driven**: as regras estão em [`specs/`](specs/README.md) e todo critério de aceite tem teste.

## Requisitos

Apenas **Docker** com o plugin Compose. PHP, Composer e Node rodam dentro dos containers do projeto; nada é instalado na sua máquina.

## Rodando

```sh
cp .env.example .env                      # na primeira vez
docker compose build
docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate   # na primeira vez
docker compose run --rm node sh -c "npm install && npm run build"
docker compose run --rm app php artisan migrate
docker compose up -d
```

Abra `http://localhost:8765`. Pelo celular, na mesma rede Wi-Fi, use `http://<ip-do-computador>:8765`
(o IP aparece com `hostname -I`).

Para trocar a porta, altere `APP_PORT` no `.env` e verifique antes se ela está livre: `ss -ltn | grep :<porta>`.

## Comandos do dia a dia

| O quê | Comando |
|---|---|
| Subir / parar | `docker compose up -d` / `docker compose down` |
| Testes | `docker compose run --rm app ./vendor/bin/pest` |
| Artisan | `docker compose run --rm app php artisan <comando>` |
| Composer | `docker compose run --rm app composer <args>` |
| Recompilar CSS/JS | `docker compose run --rm node npm run build` |
| Logs | `docker compose logs -f app` |

## Isolamento

O projeto não interfere em outros ambientes da máquina: o Compose usa o nome `financas`, com rede
e containers próprios. A única porta exposta é `APP_PORT`, e os containers rodam com o seu usuário.

## Dados pessoais

O banco (`database/financas.sqlite`), os backups, a planilha original e os fixtures privados de teste ficam fora do git.
