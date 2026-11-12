# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/); versionamento [SemVer](https://semver.org/lang/pt-BR/).

## [Não lançado]

## [0.2.0] - 2026-11-11

Correções a partir da integração do MARCELO ENVIOS com o ViPP de produção.

### Adicionado

- `VippConfig::$exigirContrato` (`exigir_contrato` / `VIPP_EXIGIR_CONTRATO`, padrão `false`): a validação local exige `ContratoEct` com `NrContrato` e `NrCartao`. Recomendado em produção.
- `ValidadorPostagem` aceita `exigirContrato` no construtor; `VippFake` e `Vipp::fake()` também.
- `ResultadoPostagem::exigeContrato()` e `ErroVipp::ehContratoObrigatorio()` para detectar o erro 91.
- `ErroVipp::$semDescricao`: indica que o ViPP mandou a descrição vazia.
- `ItemConteudo::limparDescricao()` e `ItemConteudo::CARACTERES_PERMITIDOS`, para adaptar descrições de NF-e ao que a SEFAZ aceita.
- Fixtures `producao-400-contrato-91.json` (reconstruída do relatório, sem dados reais) e `homologacao-400-cartao-sem-contrato.json` (capturada).
- Coleção Postman: `ContratoEct` nas postagens válidas (variáveis `nrContrato`/`nrCartao`) e a requisição "07 Cartão sem contrato (400 / 1100)".

### Alterado

- O erro 91 com descrição vazia agora tem uma mensagem clara: "obrigatório neste perfil (erro 91 do ViPP, sem descrição); informe ContratoEct com NrContrato e NrCartao." (antes: "Erro sem descrição").
- A validação local recusa `NrCartao` sem `NrContrato`. A homologação responde a esse caso com o erro 1100.

### Documentação

- Comportamento observado em produção: `ContratoEct` (`NrContrato` e `NrCartao`) foi obrigatório, e sem ele o ViPP devolve o erro 91 com a descrição vazia.
- README: `ContratoEct` e `exigirContrato` no exemplo de uso; seções "Contrato e cartão de postagem", "Descrição do conteúdo", "Códigos de erro observados", "Testar as credenciais" e "Credenciais vindas do banco" (Laravel).

## [0.1.0] - 2026-11-11

Primeira versão pública.

### Adicionado

- `VippClient::postarObjeto()` para o Web Service de Postagem do ViPP (REST/JSON) sobre PSR-18, com Guzzle como padrão.
- DTOs `Postagem`, `Destinatario`, `Volume`, `DeclaracaoConteudo`, `ItemConteudo`, `NotaFiscal` e `ContratoEct`, com normalização de CEP, telefones, CPF/CNPJ, valores e datas.
- `ValidadorPostagem` com as regras do manual: campos obrigatórios e tamanhos, UF, CEP, celular, CPF/CNPJ (inclusive alfanumérico), etiqueta com DV, chave de 44 dígitos, adicionais e limites da Declaração de Conteúdo.
- `ResultadoPostagem` com etiqueta, valores, prazo, erros do ViPP e `etiquetaDivergente()`.
- Exceções `ValidacaoException`, `AutenticacaoException`, `HttpException`, `TransporteException`, `RespostaInvalidaException` e `ConfiguracaoException`.
- `Testing\VippFake` para os testes de quem usa o SDK.
- Integração Laravel 12/13: `VippServiceProvider`, `config/vipp.php` e Facade `Vipp` com `fake()`.
- Coleção e ambiente Postman gerados a partir dos DTOs.

[Não lançado]: https://github.com/marcelofecchio/vipp-php-sdk/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/marcelofecchio/vipp-php-sdk/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/marcelofecchio/vipp-php-sdk/releases/tag/v0.1.0
