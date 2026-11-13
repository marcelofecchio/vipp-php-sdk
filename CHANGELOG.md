# Changelog

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/); versionamento [SemVer](https://semver.org/lang/pt-BR/).

## [Não lançado]

## [0.3.0] - 2026-11-13

Exclusão, consulta de situação e teste de credenciais, sobre endpoints que não constam no manual do ViPP.

### Adicionado

- `VippClient::excluirPostagem(string $etiqueta, ?PerfilVipp $perfil = null)` e `ResultadoExclusao` (`DeleteConhecimento`, `DELETE` com `Usuario`, `Token` e `Etiqueta` em cabeçalhos). Só exclui objetos pré-postados aguardando liberação; etiqueta sem objeto elegível volta como resultado com `excluida() === false`. O caminho de sucesso não foi verificado em ambiente real (ver README).
- `VippClientInterface::excluirPostagem()`, `VippFake::excluirPostagem()` / `responderExclusaoCom()` / `exclusoes()` / `resultadoExclusao()` e `Vipp::excluirPostagem()`.
- `VippClient::situacaoPostagem()` (`GetSituacaoPostagem`, somente leitura) com `SituacaoPostagem` e o enum `BuscarPor` (etiqueta ou referência). Permite conferir uma exportação sem resposta pela referência enviada em `codigoBarraVolume`.
- `VippClient::verificarCredenciais()` e `ResultadoConexao`: confere usuário e token sem criar postagem. O `IdPerfil` não é conferido.
- `Http\Mascara` também mascara `Senha=` e `Token=` em URLs, e a falha de rede da consulta de situação não encadeia a exceção original (a URL leva a senha).
- Fixtures capturadas da homologação para a situação (`homologacao-200-situacao-resumo`, `-completa`, `homologacao-401-situacao-login`), sem e-mail nem CPF do remetente de teste.
- `VippFake`: `responderSituacaoCom()`, `consultas()`, `situacao()`, `responderConexaoCom()` e `conexaoRecusada()`; Facade `Vipp::situacaoPostagem()` e `Vipp::verificarCredenciais()`.
- Coleção Postman: requisições 10 (situação da postagem criada na 01) e 11 (credenciais inválidas, 401). A consulta com resultado vazio (204) fica fora: o ViPP envia `Content-Length: 163` sem corpo e o Postman recusa a resposta.
- `VippConfig::urlExcluirPostagem()`, `urlSituacaoPostagem()` e `Support\Leitor::listaDeMessage()` (erros da exclusão vêm como `{"Message": "[{...}]"}`).
- Fixtures capturadas da homologação para a exclusão (`homologacao-200-exclusao-nada-excluido`, `homologacao-401-exclusao-login`, `homologacao-401-exclusao-sem-credenciais`) e uma fixture inferida (`inferida-200-exclusao-ok`).
- Coleção Postman: requisições 08 (exclusão sem objeto elegível) e 09 (exclusão com token errado), ambas seguras.

### Alterado

- **Quem implementa `VippClientInterface` por conta própria precisa adicionar `excluirPostagem()`, `situacaoPostagem()` e `verificarCredenciais()`.** O `VippFake` do pacote já tem.

### Documentação

- Respostas do suporte da VisualSet (13/11/2026): para enviar etiqueta já gerada é preciso um perfil de importação só para rastreamento; o contrato depende da configuração do perfil; `CodigoAdministrativo` não é exigido.
- README: seções "Excluir uma postagem", "Consultar a situação de uma postagem" e "Cuidados com a consulta de situação", e correção de "Testar as credenciais": o endpoint existe, ao contrário do que o suporte informou.

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

[Não lançado]: https://github.com/marcelofecchio/vipp-php-sdk/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/marcelofecchio/vipp-php-sdk/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/marcelofecchio/vipp-php-sdk/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/marcelofecchio/vipp-php-sdk/releases/tag/v0.1.0
