# Fixtures

Respostas do ViPP usadas pelos testes unitários (`respostaFixture()` em `tests/Pest.php`).

| Prefixo | Origem |
|---|---|
| `homologacao-*` | Capturadas da homologação (perfil 9363), sem edição. Para recapturar, envie o mesmo caso pela homologação e salve `ResultadoPostagem::$resposta`. |
| `producao-*` | **Reconstruídas** a partir de relatos da produção, sem dados reais. Têm só os campos confirmados pelo relato. |

- `producao-400-contrato-91.json`: relatório do `marcelo-envios` de 11/11/2026. HTTP 400, erro 91 em `NumeroContrato` e `NumeroCartao`, com `Descricao` vazia. `TipoErro` e `DescricaoTipoErro` não foram informados, por isso ficam de fora.
