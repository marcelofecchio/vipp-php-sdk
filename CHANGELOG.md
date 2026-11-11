# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/); versionamento [SemVer](https://semver.org/lang/pt-BR/).

## [Não lançado]

### Documentação

- Comportamento observado em produção, a partir da integração do MARCELO ENVIOS: `ContratoEct` (`NrContrato` e `NrCartao`) foi obrigatório, e sem ele o ViPP devolve o erro 91 com a descrição vazia.
- README: `ContratoEct` no exemplo de uso; seções "Contrato e cartão de postagem", "Descrição do conteúdo", "Códigos de erro observados", "Testar as credenciais" e "Credenciais vindas do banco" (Laravel).

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

[Não lançado]: https://github.com/marcelofecchio/vipp-php-sdk/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/marcelofecchio/vipp-php-sdk/releases/tag/v0.1.0
