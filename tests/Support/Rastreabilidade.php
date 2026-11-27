<?php

namespace Tests\Support;

/**
 * Liga os critérios de aceite (CA-xx.y) das specs aos testes que os cobrem (RNF-04).
 *
 * - Uma entrega (v1.0, v1.1, v1.2) é considerada concluída quando o seu portão
 *   (G1, G2, G3) está marcado com ☑ em specs/04-tasks.md.
 * - Todo CA de uma entrega concluída precisa aparecer no nome de ao menos um teste.
 * - Todo CA citado num teste precisa existir nas specs.
 */
final class Rastreabilidade
{
    private const PORTOES = ['G1' => 'v1.0', 'G2' => 'v1.1', 'G3' => 'v1.2'];

    private const PADRAO_CA = '/\bCA-\d{2}\.\d+\b/';

    /**
     * @return array<string, list<string>> entrega => CAs definidos nela, na ordem do documento
     */
    public static function criteriosPorEntrega(string $requisitos): array
    {
        $porEntrega = [];
        $entregaAtual = null;

        foreach (preg_split('/\R/', $requisitos) as $linha) {
            if (preg_match('/^## (v\d+\.\d+)\b/', $linha, $m)) {
                $entregaAtual = $m[1];
                $porEntrega[$entregaAtual] ??= [];

                continue;
            }

            if (str_starts_with($linha, '## ')) {
                $entregaAtual = null;

                continue;
            }

            // Só a definição do critério conta: "- **CA-01.1** ...".
            if ($entregaAtual !== null && preg_match('/^- \*\*(CA-\d{2}\.\d+)\*\*/', $linha, $m)) {
                $porEntrega[$entregaAtual][] = $m[1];
            }
        }

        return $porEntrega;
    }

    /**
     * @return list<string>
     */
    public static function entregasConcluidas(string $tarefas): array
    {
        $concluidas = [];

        foreach (self::PORTOES as $portao => $entrega) {
            if (preg_match('/^\|\s*☑\s*'.$portao.'\b/m', $tarefas)) {
                $concluidas[] = $entrega;
            }
        }

        return $concluidas;
    }

    /**
     * @param  iterable<string>  $conteudosDeTeste
     * @return list<string> CAs citados nos testes, sem repetição
     */
    public static function criteriosCitados(iterable $conteudosDeTeste): array
    {
        $citados = [];

        foreach ($conteudosDeTeste as $conteudo) {
            preg_match_all(self::PADRAO_CA, $conteudo, $m);
            array_push($citados, ...$m[0]);
        }

        return array_values(array_unique($citados));
    }

    /**
     * @param  array<string, list<string>>  $criteriosPorEntrega
     * @param  list<string>  $entregasConcluidas
     * @param  list<string>  $citados
     * @return list<string> CAs de entregas concluídas sem nenhum teste
     */
    public static function semTeste(array $criteriosPorEntrega, array $entregasConcluidas, array $citados): array
    {
        $faltando = [];

        foreach ($entregasConcluidas as $entrega) {
            foreach ($criteriosPorEntrega[$entrega] ?? [] as $ca) {
                if (! in_array($ca, $citados, true)) {
                    $faltando[] = $ca;
                }
            }
        }

        return $faltando;
    }

    /**
     * @param  array<string, list<string>>  $criteriosPorEntrega
     * @param  list<string>  $citados
     * @return list<string> CAs citados em testes que não existem nas specs
     */
    public static function inexistentes(array $criteriosPorEntrega, array $citados): array
    {
        $definidos = array_merge(...array_values($criteriosPorEntrega));

        return array_values(array_diff($citados, $definidos));
    }
}
