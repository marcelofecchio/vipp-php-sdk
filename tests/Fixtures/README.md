# Fixtures

Respostas do ViPP usadas pelos testes unitários (`respostaFixture()` em `tests/Pest.php`).

| Prefixo | Origem |
|---|---|
| `homologacao-*` | Capturadas da homologação (perfil 9363), sem edição. Para recapturar, envie o mesmo caso pela homologação e salve `ResultadoPostagem::$resposta`. |
| `producao-*` | **Reconstruídas** a partir de relatos da produção, sem dados reais. Têm só os campos confirmados pelo relato. |
| `inferida-*` | **Inventadas** a partir do formato visto em respostas parecidas, para um caminho que não foi possível exercitar. |

- `inferida-200-exclusao-ok.json`: o `DeleteConhecimento` só exclui objetos pré-postados aguardando liberação, e o perfil de homologação cria objetos já postados. O formato (`TotalConhecimentos`, `ConhecimentosExcluidos`, `ArquivosExcluidos`, `ObjetoEPPN`) é o da resposta com tudo zerado, com contagem 1. **Troque por uma captura real** quando houver um objeto elegível.
- `producao-400-contrato-91.json`: relatório do `marcelo-envios` de 11/11/2026. HTTP 400, erro 91 em `NumeroContrato` e `NumeroCartao`, com `Descricao` vazia. `TipoErro` e `DescricaoTipoErro` não foram informados, por isso ficam de fora.
- `homologacao-200-situacao-*.json`: capturadas, mas com o e-mail e o CPF do remetente de teste do ViPP trocados por valores fictícios (`remetente@example.com`, CPF de teste) antes de versionar. O resto é o que o servidor devolveu.
