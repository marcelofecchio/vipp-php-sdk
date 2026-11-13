# CLAUDE.md — SDK PHP do ViPP

## Visão geral

Pacote Composer `marcelofecchio/vipp-php-sdk` (namespace `MarceloFecchio\Vipp`): SDK para o Web Service de Postagem do **ViPP** (VisualSet), usado por agências franqueadas dos Correios. Primeiro consumidor: o `marcelo-envios` (Laravel 13), que envia ao ViPP as etiquetas que já gerou nos Correios.

- **Escopo**: `PostarObjeto` via REST/JSON, `excluirPostagem()` (`DeleteConhecimento`, fora do manual), validação local, resultado tipado, integração Laravel, fake para testes, coleção Postman.
- **Fora do escopo**: SOAP, Logística Reversa (campos `Remetente`, `TipoAutorizacaoReversa`, `DiasPrazoPostagemEticket`, `DataColeta`), eventos Laravel, envio em lote, retry automático.
- Requisitos originais: `docs/PROMPT-INICIAL.md`. Manual oficial: <https://www.visualset.com.br/layouts/WebServicePostagemViPP.pdf>.

## Stack

PHP ^8.4 · PSR-18/17/7 (Guzzle como padrão, `php-http/discovery`) · Laravel 12/13 (opcional, `src/Laravel`) · Pest 4 + Orchestra Testbench · PHPStan 2 (nível max) · PHP-CS-Fixer (PER-CS 2.0).

## Estrutura

```
src/
  VippClient.php / VippClientInterface.php   postarObjeto(), validar(), homologacao()
  Config/          VippConfig (URL, timeout, fromArray do config Laravel), PerfilVipp (token mascarado)
  Postagem/        DTOs de entrada: Postagem, Destinatario, Volume, DeclaracaoConteudo, ItemConteudo, NotaFiscal, ContratoEct
  Resultado/       ResultadoPostagem, VolumePostado, ErroVipp, ResultadoExclusao
  Validation/      ValidadorPostagem (regras do PDF), Etiqueta (DV), Documento (CPF/CNPJ alfanumérico), Violacao
  ValueObject/     Dinheiro (centavos; serializa "1234,56")
  Http/            Transport, HttpClientFactory, Mascara (esconde o token), RespostaHttp
  Enum/            ServicoAdicional, StatusPostagem
  Exceptions/      VippException (interface) + Configuracao, Validacao, Autenticacao(401), Http, Transporte, RespostaInvalida
  Testing/         VippFake
  Laravel/         VippServiceProvider, Facades/Vipp (com fake())
config/vipp.php    publicado com --tag=vipp-config
tests/Unit · tests/Laravel (Testbench) · tests/Integration (desligados por padrão) · tests/Fixtures (respostas REAIS da homologação)
postman/           gerar-colecao.php → coleção + ambiente de homologação
examples/          postar-objeto.php
```

## Comandos

```bash
composer install
composer qa                       # cs:check + analyse + test — deve passar antes de todo commit
composer test                     # unitários + Laravel
composer test:coverage            # requer pcov
composer cs:fix
VIPP_RUN_INTEGRATION=1 composer test:integration    # cria postagens REAIS na homologação
php postman/gerar-colecao.php
postman collection run postman/ViPP.postman_collection.json -e postman/Homologacao.postman_environment.json
```

No Git Bash do Windows, heredocs podem perder barras invertidas. Para arquivos com `\\` (ex.: `composer.json`), use a ferramenta de escrita de arquivos.

## Convenções

- `declare(strict_types=1)` em todos os arquivos; PSR-4; PER-CS 2.0 (`composer cs:fix`).
- **Idioma**: o domínio fica em português, como no ViPP (`postarObjeto`, `Destinatario`, `ItemConteudo`). A infraestrutura técnica fica em inglês (`Transport`, `HttpClientFactory`, `VippClient`). Documentação e mensagens em português do Brasil.
- DTOs `final readonly`, que **normalizam no construtor** (trim, só dígitos, maiúsculas) e serializam com `toArray()` em PascalCase, omitindo valores nulos.
- Dinheiro sempre via `Dinheiro` e serializado com vírgula. Peso e medidas são `int`. Datas da NF saem em `d/m/Y`.
- Respostas são lidas com `Support\Leitor`, que é tolerante a string ou int, vírgula ou ponto.
- Postagem com dados inválidos para o ViPP (HTTP 400) é **resultado**, não exceção. HTTP 401 vira `AutenticacaoException`.
- Commits no padrão Conventional Commits em português (`feat:`, `fix:`, `docs:`, `test:`, `chore:`). Branch principal `main`; features em `feat/<assunto>`.

## Regras inegociáveis

1. **Nunca** enviar postagens para credenciais de **produção** durante desenvolvimento ou testes. Use só a homologação (`onbiws` / `112233` / perfil `9363`, públicas no PDF).
2. Testes de integração só com `VIPP_RUN_INTEGRATION=1`, e eles criam postagens reais. Os unitários usam `php-http/mock-client` com as fixtures reais.
3. Nunca expor o token: `PerfilVipp::__debugInfo`, `Mascara::corpo()` e `ResultadoPostagem::$resposta` (sem `PerfilVipp`) já cuidam disso. Não regrida.
4. **Sem retry automático**: a postagem não é idempotente.
5. Um volume por `Postagem`: o ViPP descarta os volumes além do primeiro.
6. Não inventar campos, códigos ou regras. O que não estiver no PDF nem tiver sido observado na homologação vira `TODO(confirmar)` e é registrado em `docs/PROGRESSO.md`.
7. **Exclusão**: só rode `excluirPostagem()` na homologação com etiqueta **inexistente** ou de um objeto que você mesmo criou. Nunca exclua postagens de terceiros nem de produção. O sucesso real da exclusão nunca foi exercitado (ver `docs/PROGRESSO.md`).
8. Mudou o comportamento da API? Atualize as fixtures (capturando da homologação), a coleção Postman (`gerar-colecao.php`) e a seção "O que difere do manual" do README.

## Ritual de sessão

- **Início**: `git pull` → ler `CLAUDE.md` e `docs/PROGRESSO.md` → `composer install` → `composer qa`.
- **Fim**: atualizar `docs/PROGRESSO.md` (data, feito/falta/decisões) → `composer qa` → commit → `git push`.
