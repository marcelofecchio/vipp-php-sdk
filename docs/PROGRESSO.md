# PROGRESSO — vipp-php-sdk

## Estado atual

**v0.3.0 lançada em 13/11/2026** (PR, CI, tag, release no GitHub e Packagist via webhook): `excluirPostagem()`, `situacaoPostagem()` e `verificarCredenciais()` (ver histórico de 13/11/2026). `composer qa` passa, com 174 testes unitários/Laravel. A integração (8 de 8) e a coleção Postman (11 requisições, 31 de 31 asserções) passam contra a homologação. O `marcelo-envios` exporta para o ViPP de **produção** (ver "Comportamento observado em produção").

**Exclusão com sucesso ainda não verificada.** O usuário decidiu **não enviar** o segundo e-mail ao suporte (13/11/2026) e vai testar a exclusão de um envio **em produção**, pelo `marcelo-envios`. Quando houver o resultado, registrar aqui `$resultado->resposta` e o status do objeto antes da exclusão, e trocar a fixture `inferida-200-exclusao-ok.json` por uma captura real (sem dados pessoais).

**Próximo passo recomendado:** (1) registrar o resultado do teste de exclusão em produção; (2) com a AGF, criar um **perfil de importação só para rastreamento** e usá-lo no `marcelo-envios`: sem ele o ViPP troca a etiqueta enviada; (3) no `marcelo-envios`, atualizar para `^0.3`, ligar `exigirContrato`, usar `ItemConteudo::limparDescricao()`, `exigeContrato()`, `verificarCredenciais()` no "Testar conexão" e `situacaoPostagem(…, BuscarPor::Referencia)` antes de reenviar uma exportação sem resposta.

## Histórico

### 13/11/2026 — resposta do suporte da VisualSet e `excluirPostagem()`

Perguntas enviadas ao suporte (exclusão e dúvidas da produção); respostas e o que foi feito:

| # | Pergunta | Resposta do suporte | Verificado |
|---|---|---|---|
| 1, 4 | `DeleteConhecimento` é liberado para integrações? Regras? | Exclui qualquer objeto **pré-postado com status aguardando liberação** | Parcial: o perfil 9363 cria objetos já postados, então o sucesso não é reproduzível |
| 2, 3 | Parâmetros e autenticação | **Cabeçalhos** `Usuario`, `Token` e `Etiqueta` (sem `IdPerfil`); método não informado | Sim: `DELETE` funciona. `POST` também respondeu 200. Com `Senha` em vez de `Token`, dá 401 (erro 1010). Em query string ou corpo JSON, dá 401 (erro 1000) |
| 5 | Libera a etiqueta? | **Não** libera: é preciso refazer | Não testável |
| 6 | Formato da resposta | "IDconhecimento 0 erro, 1 sucesso" | **Não confere**: a resposta real é `{TotalConhecimentos, ConhecimentosExcluidos, ArquivosExcluidos, ObjetoEPPN}` |
| 7 | Alternativa (`BloquearDownloadConhecimento`) | Resposta contraditória ("não existe", mas "pode ser usado para objetos criados por integração") | O WSDL SOAP lista `BloquearDownloadConhecimento`, `LiberarDownloadConhecimento` e `BloquearObjetoCorreios`; **não implementados** (SOAP fora do escopo) |
| 8 | Perfil de produção mantém a etiqueta? | Para enviar objeto com etiqueta gerada, é preciso **um perfil de importação apenas para rastreamento** | Não testável (sem esse perfil na homologação) |
| 9 | Cartão sem zeros? `CodigoAdministrativo`? | "Não exige" (só `CodigoAdministrativo`; os zeros ficaram sem resposta) | Parcial |
| 10 | Contrato obrigatório (erro 91)? | Depende do perfil: "ViPP importar" dispensa; "respeitar a chamada" exige | Sim, coerente com o observado |
| 11 | Há endpoint para validar credenciais? | "Não existe" | **Desmentido** (ver abaixo) |

Achados da sondagem (homologação, só com postagens de teste criadas por mim):
- A documentação interativa do servidor (`/swagger/docs/v4`) lista 63 endpoints, bem mais que o manual. Há `DeleteConhecimento` (POST e DELETE, sem parâmetros documentados).
- **`GET /api/v1/conhecimento/GetSituacaoPostagem` valida credenciais sem criar postagem** (401/1030 com senha errada; 204 com senha certa e etiqueta inexistente). Parâmetros: `Usuario`, `Senha`, `StDadosCompletos` (`0` resumo, `1` completo), `BuscarPor` (`EtiquetaPostagem`, `ObservacaoUm`, `ObservacaoDois`), `ListaObjeto` (uma etiqueta; vírgula, `;`, `|` e espaço não funcionaram para duas). Também devolve a situação completa da postagem. Os itens `verificarCredenciais()` e `situacaoPostagem()` ficam no backlog.
- `DeleteConhecimento` com etiqueta inexistente ou **sem objeto elegível** responde **200 com tudo zerado**, e não erro. Os objetos criados pelo `PostarObjeto` na homologação continuaram existindo depois do `DELETE` (conferido por `GetSituacaoPostagem`).
- Um `DELETE` com outro nome de cabeçalho para a etiqueta (`EtiquetaPostagem`, `NrEtiquetaPostagem`) deu 400 com erro de SQL vazando o nome da rotina (`ConhecimentoListarParaExcluirPPN`, parâmetro `@ValorDaBusca`). Ou seja, o cabeçalho correto é `Etiqueta`.
- Os erros 401 da exclusão têm outro formato: `{"Message": "[{...}]"}` (JSON dentro de string).

**Segunda parte (mesma branch): `situacaoPostagem()` e `verificarCredenciais()`** sobre `GetSituacaoPostagem`:
- `situacaoPostagem(valor, BuscarPor, completo, perfil)` devolve `list<SituacaoPostagem>` (vazia no 204). `BuscarPor`: `Etiqueta` (`EtiquetaPostagem`), `Referencia` (`ObservacaoUm`, que é o `CodigoBarraVolume` enviado) e `ReferenciaDois` (listado pela API, não exercitado). Um valor por chamada.
- Conferido: `ObservacaoUm` localiza a postagem pela referência enviada. Isso resolve a recuperação de exportação sem resposta (timeout) do `marcelo-envios`: consultar por `ME-{id}` antes de reenviar. Há teste de integração criando uma postagem e encontrando-a pela referência.
- `verificarCredenciais()` consulta uma etiqueta inexistente (`SQ000000005BR`) e devolve `ResultadoConexao` (401 não vira exceção). **Não confere o `IdPerfil`** (o endpoint não o recebe).
- **Senha na URL**: `Mascara` passou a mascarar `Senha=`/`Token=` e `Transport::get()` mascara a mensagem de falha de rede e **não encadeia** a exceção original, que traria a URL com a senha.
- **204 malformado**: o servidor responde `204 No Content` com `Content-Length: 163` e sem corpo. cURL/Guzzle toleram; o Postman falha (`Parse Error`), então a coleção não tem a requisição "204". Clientes PSR-18 estritos podem falhar (virariam `TransporteException`).
- Fixtures capturadas e **sanitizadas** (e-mail de uma pessoa e CPF do remetente de teste trocados por valores fictícios).
- Postman: 10 (situação da postagem criada na 01) e 11 (401/1030).

Feito no SDK (branch `feat/excluir-postagem`): `excluirPostagem()` + `ResultadoExclusao` (`excluida()` = `ConhecimentosExcluidos > 0`), `VippFake`/`Vipp::fake()`, fixtures (3 capturadas e 1 inferida), testes unitários e de integração (só com etiqueta inexistente), Postman 08 e 09, README/CHANGELOG/CLAUDE.md. **Limitação conhecida:** o caminho de sucesso real não foi exercitado, e a regra "excluída = `ConhecimentosExcluidos > 0`" é uma inferência.

### 11/11/2026 — v0.2.0 (backlog do relatório do `marcelo-envios`)

Feito, na branch `feat/contrato-erro91-descricao` (PR com merge em `main`):
- `exigirContrato` em `VippConfig` (`exigir_contrato` / `VIPP_EXIGIR_CONTRATO`, padrão `false`), `ValidadorPostagem`, `VippFake` e `Vipp::fake()`.
- Erro 91 sem descrição: mensagem clara, `ErroVipp::$semDescricao`, `ErroVipp::ehContratoObrigatorio()` e `ResultadoPostagem::exigeContrato()`.
- `ItemConteudo::limparDescricao()` e `ItemConteudo::CARACTERES_PERMITIDOS`, com a regex compartilhada com o validador.
- Sondagem na homologação: o perfil 9363 **impõe o próprio contrato (`35`)** e ignora `NrContrato`/`NrCartao` enviados (contrato inexistente, só contrato e cartão sem zeros foram aceitos). **Cartão sem contrato → HTTP 400, erro 1100** ("O Número do Cartão É Obrigatóriamente Numérico!"). A validação local passou a barrar esse caso sempre.
- Fixtures: `homologacao-400-cartao-sem-contrato.json` (capturada) e `producao-400-contrato-91.json` (reconstruída do relatório; ver `tests/Fixtures/README.md`). O erro 91 não se reproduz na homologação.
- Postman: `ContratoEct` com as variáveis `nrContrato`/`nrCartao` nas postagens válidas e a requisição 07 (erro 1100).
- Decisão: `exigirContrato` com padrão `false`. Ligar por padrão quebraria quem usa a homologação sem contrato e contraria o manual (Nota 1). A recomendação de ligar em produção está no README e no `config/vipp.php`.

### 11/11/2026 — relatório da integração do `marcelo-envios` com a produção

O `marcelo-envios` integrou o SDK v0.1.0 com o ViPP de produção e enviou um relatório de lacunas (`vipp-php-sdk-gaps.md`, 6 itens). Feito nesta sessão, **só documentação**, sem mudança de comportamento:
- README: `ContratoEct` no exemplo de uso; novas seções "Contrato e cartão de postagem", "Descrição do conteúdo" (com um trecho para limpar a descrição), "Códigos de erro observados", "Testar as credenciais" e "Credenciais vindas do banco" (Laravel). "O que difere do manual" passou a cobrir também a produção.
- Comentários de `ContratoEct` e `ErroVipp` atualizados (erro 91).
- Packagist confirmado: pacote publicado, webhook `packagist.org/api/github` ativo e `composer require` testado em projeto limpo.
- As sugestões de código do relatório foram para o "Backlog" e entraram na v0.2.0.

### 11/11/2026 — lançamento da v0.1.0

Feito:
- `composer validate --strict` e `composer qa` sem erros; CHANGELOG fechado em `[0.1.0]`.
- Tag `v0.1.0` e release no GitHub.
- Não havia token da API do Packagist nesta máquina, então o cadastro do pacote ficou com o usuário (packagist.org → Submit).

### 30/10/2026 — sessão inicial

Feito:
- Fundação: Git, Composer, Pest 4 + Testbench, PHPStan max, PHP-CS-Fixer, CI no GitHub Actions (PHP 8.4; 8.5 experimental).
- Sondagem da homologação para resolver as contradições do PDF (seção 4.6 do prompt). Resultados abaixo.
- Núcleo: DTOs de postagem, `Dinheiro`, validação local (`ValidadorPostagem`, DV da etiqueta, CPF/CNPJ alfanumérico), `VippClient` sobre PSR-18, resultado tipado, hierarquia de exceções, `VippFake`.
- Laravel: `VippServiceProvider` (auto-discovery, config publicável, singleton, credenciais lidas sob demanda) e Facade `Vipp` com `fake()`.
- Testes: unitários com fixtures **reais** da homologação, testes Laravel e integração opt-in.
- Coleção Postman gerada a partir dos DTOs; README, CLAUDE.md e exemplo.

## Comportamento observado na homologação (30/10/2026)

Perfil `9363`, usuário `onbiws`. Cada item foi testado com requisições reais.

| # | Ponto do PDF | Observado | Decisão no SDK |
|---|---|---|---|
| 1 | `http` × `https` | Os dois funcionam | `https` como padrão; URL base configurável |
| 2 | URL de homologação | Não existe uma URL própria; o ambiente é definido pelas credenciais | Uma única `URL_PADRAO` |
| 3 | Formato de data | `15/07/2026` funciona; `15072026`, `07152026` e `2026-07-15` dão **erro 1500** ("Deve Estar no Formato DD/MM/YYYY ou MM/DD/YYYY"). A Nota 8 está errada | Envia `d/m/Y` e converte `Y-m-d` |
| 4 | Valores monetários | `1234,00`, `1234.00` e `123400` viram 1234.00, mas `"1234"` virou 25.63 (o mínimo do VD) e `"10"` virou 0,10: **número sem separador = centavos** | Sempre `"1234,56"` (vírgula + 2 casas) |
| 5 | CEP | `01203-001` é normalizado; `1203001` (7 dígitos) é **aceito como está** | Normaliza e exige 8 dígitos |
| 6 | Posição da DeclaracaoConteudo | Aninhada em `Volumes[].DeclaracaoConteudo` funciona | Aninhada |
| 7 | Maiúsculas/minúsculas nas chaves | `volumes`/`peso` em camelCase funcionam (a API não diferencia) | PascalCase |
| 8 | Peso | `"13.0"` → 13; `"1,6"` → **1** (truncado); int JSON funciona | Só `int` em gramas |
| 9 | `Celular` × `TelefoneSMS` | `Celular` aceito | `Celular` |
| 10 | Onde vem o retorno | Dentro de `Volumes[0]` (Etiqueta, ValorTarifa, ValorAdicionais, ValorPostagem, StEntregaSabado, StEntregaDomiciliar, DiasUteisPrazo, IdConhecimento, Categoria, CodigoAdicionaisVolume) | `VolumePostado` |
| 11 | `Accept-Encoding: UTF-8` | Desnecessário | Enviado mesmo assim, por compatibilidade |

Outros achados:

- **Status HTTP:** validação → **400** com `ListaErros`; credencial inválida → **401** (erro 1030 "Usuário ou Senha Inválidos!"); perfil inexistente ou sem acesso → **401** (erro 1002). JSON malformado → 400 com os erros de campos obrigatórios.
- **Vários volumes:** só `Volumes[0]` é processado; os demais são descartados sem aviso, mesmo com DV inválido no segundo. **Decisão:** a `Postagem` tem exatamente um `Volume`.
- **Etiqueta informada:** com DV válido (`SQ123456785BR`, `AA123456785BR`) ou repetida, a homologação **ignora a etiqueta e gera outra** (`SQ0003527xxBR`). Com DV inválido, dá erro **2002**. **Decisão:** o SDK valida formato e DV, guarda a etiqueta enviada e expõe `ResultadoPostagem::etiquetaDivergente()`.
- **Serviço:** `ServicoECT` enviado (`03298`, `03220`) volta como `4162`, porque o perfil 9363 impõe o serviço. `ResultadoPostagem::$servicoEct` mostra o serviço efetivamente usado.
- **Valor declarado:** ao informar `ValorDeclarado`, o ViPP adiciona sozinho o adicional `VD` (código 19). Abaixo do mínimo, aplica o mínimo (R$ 25,63).
- **Descrição do item:** 2 caracteres dão erro 2200; 3 caracteres são aceitos. **Decisão:** mínimo de 3.
- **A homologação aceita** emoji na descrição, item acima de R$ 100 mil, quantidade 0, declaração sem itens, CPF inválido, chave de 44 dígitos com 3 dígitos, adicional desconhecido (`XX`), UF incoerente com o CEP e destinatário sem número, bairro ou cidade. Como o PDF e as regras dos Correios e da SEFAZ exigem esses dados, **o SDK valida localmente** (pode ser desligado com `validar_antes_de_enviar=false`).
- Campos extras na resposta: `EntregaNoVizinho`, `QuantidadeVolumes`, `IdStatusPostagem`, `CodigoAdicionaisVolume`. A resposta **ecoa o `PerfilVipp` com o token**, que o SDK remove de `ResultadoPostagem::$resposta` e mascara nas exceções.

## Comportamento observado em produção (11/11/2026)

Fonte: relatório da integração do `marcelo-envios` (`vipp-php-sdk-gaps.md`), que exporta envios já pré-postados nos Correios, com a etiqueta dos Correios. Não houve postagens de produção feitas a partir deste repositório (regra 1 do CLAUDE.md).

| # | Ponto | Observado | Situação |
|---|---|---|---|
| 1 | `ContratoEct` | Sem ele: HTTP 400 com `[91] NumeroContrato` e `[91] NumeroCartao`, **descrição vazia**. Com `NrContrato` + `NrCartao`, a mesma postagem foi aceita. A homologação (perfil 9363) não exige | v0.2.0: `exigirContrato`, mensagem clara e `exigeContrato()` |
| 2 | Testar credenciais | O ViPP só tem `PostarObjeto`; não existe consulta nem login sem criar postagem | Documentado. `TODO(confirmar)` com a VisualSet se existe algum endpoint de consulta |
| 3 | Credenciais no banco | O app sobrescreve o vínculo de `VippClientInterface` (`bind`), montando `VippConfig` a partir do banco; `Vipp::fake()` continua funcionando | Documentado (README → Laravel) |
| 4 | Etiqueta divergente | Ainda não se sabe se o perfil de produção mantém a etiqueta enviada | `TODO(confirmar)` |
| 5 | Formato do contrato e do cartão | Só dígitos com os zeros à esquerda (cartão com 10 dígitos): aceito. `CodigoAdministrativo` não enviado e não exigido | Sem zeros e `CodigoAdministrativo` em outros perfis: `TODO(confirmar)` |
| 6 | Descrição do conteúdo | Descrições de NF-e trazem `#`, `&`, aspas e `*`, recusados pela validação local, e o app precisou limpar antes de enviar | v0.2.0: `ItemConteudo::limparDescricao()` |

Também confirmado: a produção usa a mesma URL padrão (`https://vpsrv.visualset.com.br`). O app monta `new VippConfig($perfil)` sem `baseUrl` e as postagens são aceitas.

## Backlog (sugestões de código do relatório do `marcelo-envios`)

- [x] `VippConfig::$exigirContrato` (`VIPP_EXIGIR_CONTRATO`), padrão `false` (v0.2.0).
- [x] Erro 91 sem descrição com mensagem clara + `exigeContrato()` (v0.2.0).
- [x] Fixture `producao-400-contrato-91.json` e testes (v0.2.0).
- [x] `ItemConteudo::limparDescricao()` (v0.2.0).
- [x] `verificarCredenciais()` e `situacaoPostagem()` sobre `GetSituacaoPostagem` (13/11/2026, branch `feat/excluir-postagem`).
- [ ] Consulta de situação com **mais de um valor** por chamada (o formato de `ListaObjeto` com vários valores é desconhecido; ver a pergunta ao suporte) e rastreamento (`ListarRastreioObjeto`).
- [x] `excluirPostagem()` sobre `DeleteConhecimento` (13/11/2026, branch `feat/excluir-postagem`).

## Decisões

| Decisão | Motivo |
|---|---|
| Só REST/JSON; sem Logística Reversa | Definido no `PROMPT-INICIAL.md` |
| Postagem HTTP 400 = resultado; 401 = `AutenticacaoException` | 400 diz respeito aos dados (o chamador corrige e reenvia). 401 é configuração e não se resolve mudando a postagem |
| Sem retry automático | A postagem não é idempotente; o reenvio após timeout pode duplicar o objeto. `HttpException::ehTemporario()` ajuda quem quiser repetir |
| Núcleo sem `illuminate/*` no `require` | O SDK continua agnóstico. A ponte Laravel só é carregada pela auto-discovery, quando o Laravel existe |
| Laravel 12 e 13 | O `marcelo-envios` usa Laravel 13.35; o 12 tem custo zero de suporte |
| Padrões copiados do `siscomex-sdk` | MIT, PHPStan max, PER-CS 2.0, Conventional Commits, CI, gerador Postman, mesmo ritual de sessão |
| Descrição da DC: letras, dígitos, espaço e `. , - / ( ) % : ; +` | Interpretação conservadora de "sem caracteres especiais" (Nota 17). Ajustar se a SEFAZ ou os Correios recusarem algo permitido |
| CNPJ alfanumérico aceito | Começa a valer em 2026; o algoritmo de DV (ord − 48) também cobre o CNPJ numérico |
| Eventos Laravel não implementados | O prompt pedia confirmação antes; podem ser adicionados depois sem quebrar a API |

## Pontos em aberto

- [x] Perfil de produção e etiqueta enviada: o suporte disse que é preciso um **perfil de importação só para rastreamento** (13/11/2026). **Pendente:** a AGF criar esse perfil e o `marcelo-envios` usá-lo; confirmar com `etiquetaDivergente()` falso na primeira postagem.
- [x] `CodigoAdministrativo` não é exigido (suporte, 13/11/2026). **Sem resposta:** se o cartão é aceito sem os zeros à esquerda.
- [x] ~~Endpoint de consulta/login~~: o suporte disse que não existe, mas `GetSituacaoPostagem` existe (13/11/2026); ver Backlog.
- [ ] **TODO(confirmar)**: formato real da resposta de **sucesso** da exclusão (o suporte falou em "IDconhecimento 0/1", e o servidor devolve contagens). Será verificado pelo teste do usuário em produção (13/11/2026). O segundo e-mail ao suporte foi preparado, mas o usuário decidiu não enviar; as perguntas dele continuam como `TODO(confirmar)` nesta lista.
- [ ] **TODO(confirmar)**: formato de `ListaObjeto` com várias etiquetas no `GetSituacaoPostagem`; valores de `BuscarPor` (`ObservacaoDois`); e se o `204` com `Content-Length: 163` sem corpo é intencional.
- [ ] **TODO(confirmar)**: significado de `ObjetoEPPN` na resposta da exclusão, e de `BloquearDownloadConhecimento`/`BloquearObjetoCorreios` (resposta contraditória do suporte).
- [x] O servidor de produção é o mesmo `vpsrv.visualset.com.br`, só com outras credenciais (11/11/2026, pela integração do `marcelo-envios`).
- [ ] Conjunto exato de caracteres aceitos pela SEFAZ na descrição da DC-e.
- [ ] Eventos Laravel (postagem enviada, válida ou inválida)? Decidir com o usuário.
- [x] Tag `v0.1.0` e release no GitHub (11/11/2026).
- [x] Packagist (`marcelofecchio/vipp-php-sdk`) com webhook de atualização automática; `composer require` testado em projeto limpo (11/11/2026).
- [x] Integração no `marcelo-envios` (11/11/2026): botão "Exportar para o ViPP", uma tentativa por clique, trava por envio, sem retry; `CodigoBarraVolume = ME-{id}` para conferir exportações sem resposta.
