# PROMPT INICIAL — SDK PHP para integração com o ViPP

> **Destinatário:** agente LLM responsável por desenvolver o projeto `marcelofecchio/vipp-php-sdk`.
> **Origem:** gerado a partir de `docs/Caderno de anotação iniciais.md`, das respostas do responsável pelo projeto e da análise da documentação oficial do ViPP (versão "Padronização de Layout — Junho 2026").
> **Idioma de trabalho:** português (código, documentação e comunicação com o usuário).

---

## 1. Missão

Desenvolver um **SDK em PHP 8.4**, independente e reutilizável, para integração com o **Web Service de Postagem e Logística Reversa do ViPP** (VisualSet Tecnologia), preparado para publicação no **Packagist** como:

```
marcelofecchio/vipp-php-sdk
```

O mesmo nome será usado no repositório do GitHub.

---

## 2. Contexto de negócio

- O **ViPP** é um sistema usado por algumas **agências franqueadas dos Correios (AGF)**.
- Ele permite que os clientes da agência enviem ao sistema da agência os dados das etiquetas geradas pela API dos Correios, o que agiliza a operação e a logística da agência.
- O SDK será consumido pelo sistema de envios **`marcelo-envios`**, que já existe e já gera etiquetas pela API dos Correios. Em uma etapa futura, o `marcelo-envios` terá uma interface para exportar ao ViPP os dados dessas etiquetas.
- **Responsabilidade do SDK:** fazer toda a comunicação entre o `marcelo-envios` e o ViPP. O SDK **não** depende do `marcelo-envios` e não pode conter regras específicas dele.

### 2.1. Caso de uso principal

O `marcelo-envios` **envia o número da etiqueta já gerada nos Correios** no campo `Etiqueta` (Nota 16 da documentação). Por isso:

- o SDK deve aceitar e enviar a etiqueta existente;
- o SDK deve validar, antes do envio, o formato da etiqueta (`AA123456785BR`: 2 letras, 8 dígitos, 1 dígito verificador e o sufixo `BR`) e o dígito verificador (algoritmo módulo 11 dos Correios, com pesos `8 6 4 2 3 5 9 7`);
- o campo continua opcional na API. Sem ele, o ViPP gera a etiqueta conforme o Perfil de Importação, e o SDK não deve impedir esse fluxo.

---

## 3. Decisões já tomadas

| Tema | Decisão |
|---|---|
| Linguagem | PHP **8.4** (`"php": "^8.4"`) |
| Protocolo | **Somente REST/JSON**. SOAP fora do escopo. |
| Logística Reversa | **Fora do escopo.** As etiquetas de reversa já chegam prontas ao `marcelo-envios` pela API dos Correios. |
| Cliente HTTP | **PSR-18** (`psr/http-client`) + **PSR-17** (`psr/http-factory`). O consumidor injeta a implementação (Guzzle, Symfony HttpClient etc.); descoberta automática opcional via `php-http/discovery`. |
| Testes automatizados | **Pest** |
| Testes manuais da API | Coleção **Postman** (o Postman CLI está instalado na máquina do desenvolvedor) |
| Idioma dos nomes | **Português**, espelhando os campos do ViPP (ex.: `Destinatario`, `NotaFiscal`, `Volume`, `DeclaracaoConteudo`, `postarObjeto()`) |
| Distribuição | Pacote Composer publicável no Packagist |
| Framework alvo | **Preferencialmente Laravel**, com uma camada de integração própria (seção 5.3). O núcleo do SDK continua utilizável em PHP puro. |

Não reabra essas decisões sem um motivo concreto. Se surgir um motivo, pergunte ao usuário.

---

## 4. Documentação de referência (fonte da verdade)

**Leia por inteiro, antes de qualquer implementação:**
<https://www.visualset.com.br/layouts/WebServicePostagemViPP.pdf>

Nas tabelas de definição de campos (páginas 5 a 7), as colunas desalinham quando o texto é extraído do PDF. **Confira tamanhos, tipos e obrigatoriedade olhando o PDF renderizado**, não só o texto extraído.

O resumo abaixo serve para orientar. Ele não substitui o PDF.

### 4.1. Endpoint e requisição

| Item | Valor |
|---|---|
| Método | `POST` |
| URL | `https://vpsrv.visualset.com.br/api/v1/middleware/PostarObjeto` |
| Headers | `Content-Type: application/json` e `Accept-Encoding: UTF-8` (conforme a documentação) |
| Corpo | JSON |

Credenciais de **homologação** publicadas na documentação:

| Usuario | Token | IdPerfil |
|---|---|---|
| `onbiws` | `112233` | `9363` |

As credenciais de produção serão fornecidas pela AGF. **Nunca** coloque credenciais no código-fonte do SDK. Nos testes e no Postman, use variáveis de ambiente.

### 4.2. Estrutura do payload (`PostarObjeto`)

```
PerfilVipp            { Usuario*, Token*, IdPerfil* }
ContratoEct           { NrContrato, CodigoAdministrativo, NrCartao }        (Nota 1)
Destinatario          { CnpjCpf, IeRg, Nome*, SegundaLinhaDestinatario, Endereco*, Numero,
                        Complemento, Bairro, Cidade, UF, Cep*, Telefone, Celular, Email }
Servico               { ServicoECT }                                         (Nota 7)
NotasFiscais[]        { DtNotaFiscal, SerieNotaFiscal, NrNotaFiscal, VlrTotalNota,
                        ProtocoloNotaFiscal, ObservacaoNotaFiscal }
Volumes[]             { Peso, Altura, Largura, Comprimento, ContaLote, ChaveRoteamento,
                        CodigoBarraVolume, CodigoBarraCliente, ObservacaoVisual,
                        ObservacaoQuatro, ObservacaoCinco, PosicaoVolume, Conteudo,
                        DeclaracaoConteudo { DocumentoRemetente, DocumentoDestinatario,
                                             ItemConteudo[] { DescricaoConteudo*, Quantidade*, Valor* } },
                        ValorDeclarado, AdicionaisVolume, VlrACobrar, Etiqueta }
```

`*` marca os campos confirmadamente obrigatórios (pela tabela, pelas notas ou pelos exemplos de erro). Confirme os demais no PDF.

### 4.3. Regras de negócio (Notas Técnicas 1 a 18)

| Nota | Regra resumida |
|---|---|
| 1 — Perfil | Contrato, código administrativo, cartão e `ServicoECT` só são obrigatórios quando **não** estiverem configurados no Perfil de Importação. O SDK não sabe como o perfil está configurado, então **não deve torná-los obrigatórios localmente**. |
| 2 — CPF/CNPJ | Apenas dígitos, sem formatação. Obrigatório para gerar a DCe. |
| 3 — 2ª linha | "Entrega no Vizinho" (só PAC/SEDEX). Exige o adicional `EV`. |
| 4 — UF | Exatamente 2 letras maiúsculas, coerentes com o CEP. |
| 5 — CEP | 8 dígitos, preservando zeros à esquerda. |
| 6 — Celular | Formato `DDNNNNNNNNN` (11 dígitos). Obrigatório para entregas em LOCKER. |
| 7 — ServicoECT | Código do serviço (ex.: SEDEX `03220`, PAC `03298`). |
| 8 — Datas | Ver inconsistências (seção 4.6). |
| 9 — Valores | Ver inconsistências (seção 4.6). `ValorDeclarado` é ignorado se o perfil usar o valor da NF. |
| 10 — NF | Protocolo de autorização com 20 dígitos. Observação com até 600 caracteres. |
| 11 — Peso | Em **gramas**, inteiro (1,6 kg = `1600`). Peso e dimensões ausentes são preenchidos com os mínimos dos Correios. |
| 12 — Chave de roteamento | Recurso do ViPP. Apenas repassar o valor. |
| 13 — `CodigoBarraVolume` | Referência interna indexada (ex.: número do pedido). Recomendado para o `marcelo-envios` cruzar os dados. |
| 14 — `CodigoBarraCliente` | Chave da NF-e (DANFE) ou da DCe, com **44 dígitos**. |
| 15 — Adicionais | Siglas de 2 caracteres separadas por espaço: `AR MP AP AD OF AT EE EV RM RP RS`. `AP` é obrigatório para artigos restritos (ex.: baterias). |
| 16 — Etiqueta | 13 caracteres, válida, ou a postagem é recusada (ver seção 2.1). |
| 17 — Declaração de Conteúdo | **Obrigatória**, com pelo menos 1 item. A descrição não pode ter caracteres especiais nem emojis. Cada item pode somar no máximo R$ 100.000,00 (quantidade × valor) e o total no máximo R$ 200.000,00. |
| 18 — Logística Reversa | **Fora do escopo** deste SDK (seção 5.4). |

### 4.4. Resposta

**Sucesso:** `StatusPostagem = "Valida"` e `StPostagem = 1`. Os dados de cada volume vêm acrescidos de `Etiqueta`, `ValorTarifa`, `ValorAdicionais`, `ValorPostagem`, `StEntregaSabado`, `StEntregaDomiciliar`, `DiasUteisPrazo` e, possivelmente, `Cubagem` e `Categoria`. Quando o peso ou as dimensões estiverem abaixo do mínimo, o retorno traz os valores mínimos aplicados. Campos que não foram enviados não voltam na resposta.

**Falha de validação:** `StatusPostagem = "Invalida"`, `StPostagem = 0`, `IdStatusPostagem = 0` e a lista `ListaErros[]` com:

```
{ TipoErro, DescricaoTipoErro, CodigoErro, Campo, Descricao }
```

Exemplos: `1000` Usuario, `1010` Token, `1020` IdPerfil, `1310` Nome, `1320` Endereco, `1330` Cep, `2200` DescricaoConteudo, `2222` Valor.

### 4.5. Códigos HTTP documentados

`200` e `201` indicam sucesso. Erros: `400`, `401`, `404`, `423`, `429`, `500`, `504`, `507` e `509`. Atenção: uma resposta HTTP 200 pode conter `StatusPostagem = "Invalida"`.

### 4.6. Inconsistências da documentação (validar em homologação)

O PDF tem contradições. **Não escolha uma interpretação por conta própria.** Valide cada uma com requisições reais no ambiente de homologação (pela coleção Postman), registre o comportamento observado em `docs/PROGRESSO.md` e só então implemente.

1. **Protocolo da URL:** a página 12 usa `http://` e a página 28 usa `https://`. Prefira `https` e deixe a URL base configurável.
2. **Ambiente de homologação:** a documentação não informa uma URL própria para homologação; aparentemente o ambiente é definido só pelas credenciais. Confirme.
3. **Formato de data:** a tabela fala em `DD/MM/YYYY` e os exemplos usam `15/07/2020`, mas a Nota 8 diz que só aceita `DDMMAAAA` ou `MMDDAAAA`, sem separadores.
4. **Valores monetários:** a Nota 9 pede o valor sem separador (R$ 1.234,00 = `123400`), mas os exemplos usam `"1604,00"`, `"125,98"` e `"5000,10"`, e a resposta devolve `"1604.00"`.
5. **CEP:** a Nota 5 exige 8 dígitos, mas o exemplo envia `"01203-001"`. O SDK deve sempre enviar 8 dígitos.
6. **Posição da `DeclaracaoConteudo`:** o primeiro exemplo REST é um JSON malformado, com `ItemConteudo` fora do volume. Os demais exemplos colocam `Volumes[].DeclaracaoConteudo.ItemConteudo[]`. Use a forma aninhada.
7. **Maiúsculas e minúsculas das chaves:** o exemplo de Logística Reversa usa `notasFiscais`, `volumes`, `peso` e `declaracaoConteudo`, e o resto da documentação usa PascalCase. Use PascalCase e verifique se a API diferencia maiúsculas de minúsculas.
8. **Peso:** a Nota 11 pede gramas inteiros, mas o exemplo de reversa envia `"peso": 13.0`.
9. **Celular:** o REST usa `Celular` e o SOAP usa `TelefoneSMS`. Use `Celular`.
10. **Localização dos dados de retorno no REST:** `Etiqueta`, `ValorTarifa` etc. aparecem fora do volume no exemplo REST e dentro de `VolumeObjeto` no SOAP. Confirme a estrutura real e trate os dois casos se for preciso.
11. **Header `Accept-Encoding: UTF-8`:** o valor não é padrão HTTP. Envie como a documentação pede e confirme se é necessário.

---

## 5. Requisitos do SDK

### 5.1. Funcionais

1. **Postagem de objeto** (`PostarObjeto`) com todos os campos da seção 4.2.
2. **Configuração:** usuário, token, id do perfil, URL base e, opcionalmente, um timeout. Deve ser possível usar perfis diferentes por requisição (Nota 1).
3. **Normalização** dos dados de entrada: remover a formatação de CPF/CNPJ, CEP e telefone, converter o peso para gramas, formatar valores e datas conforme o que for confirmado em homologação (seção 4.6).
4. **Validação local** das regras determinísticas (seção 4.3), para falhar antes da chamada HTTP com mensagens claras. As regras que dependem do perfil ficam a cargo da API.
5. **Resposta tipada:** um objeto de resultado que exponha sucesso ou falha, a etiqueta, os valores, o prazo, as flags de entrega, a lista de erros da API e o payload bruto (para depuração).
6. **Exceções** em hierarquia própria, separando: erro de configuração, erro de validação local, erro de transporte/HTTP (por código) e resposta inválida ou impossível de interpretar. Uma postagem `"Invalida"` **não** deve virar exceção: ela é um resultado.

### 5.2. Não funcionais

- PHP 8.4: use os recursos modernos da linguagem quando eles trouxerem clareza (readonly, enums, property hooks, tipos estritos e `declare(strict_types=1)`).
- Dependências mínimas em produção (só as interfaces PSR, mais `php-http/discovery` se adotado).
- Classes pequenas, imutáveis sempre que possível e fáceis de testar.
- PSR-4 sob o namespace raiz `MarceloFecchio\Vipp` (confirme com o usuário se preferir outro).
- O SDK não pode registrar em log nem expor o `Token`. Se a exceção ou o log incluir o payload, mascare o token.
- Nada específico do `marcelo-envios` dentro do SDK.

### 5.3. Integração com Laravel

O SDK será usado **preferencialmente em aplicações Laravel** (o `marcelo-envios` é o primeiro consumidor). Ofereça uma integração de primeira classe, isolada do núcleo:

- Estrutura em duas camadas: o **núcleo agnóstico** (ex.: `src/`) não depende de nenhum pacote `illuminate/*`; a **ponte Laravel** fica em um namespace próprio (ex.: `MarceloFecchio\Vipp\Laravel`).
- **Service Provider** registrado por **package auto-discovery** (`extra.laravel.providers` no `composer.json`), que faça o bind do cliente no container (singleton) e resolva o cliente PSR-18/17 automaticamente. O Guzzle, já presente no Laravel, implementa PSR-18.
- **Arquivo de configuração publicável** (`config/vipp.php`, via `php artisan vendor:publish --tag=vipp-config`) lendo as variáveis de ambiente, por exemplo: `VIPP_BASE_URL`, `VIPP_USUARIO`, `VIPP_TOKEN`, `VIPP_ID_PERFIL` e `VIPP_TIMEOUT`.
- **Facade** opcional (ex.: `Vipp::postarObjeto(...)`, com alias pela auto-discovery) e suporte a **injeção de dependência** do cliente em controllers, jobs e services.
- As dependências `illuminate/*` devem ficar fora do `require` de produção do núcleo, ou restritas ao mínimo necessário. Avalie e justifique a escolha no `PROGRESSO.md`.
- Pensar no uso dentro de **filas/jobs** do Laravel: o cliente não pode guardar estado mutável entre requisições, e os resultados e exceções devem ser fáceis de registrar em log (com o token mascarado).
- Na ponte Laravel, o SDK pode **disparar eventos** (ex.: postagem enviada, válida ou inválida). Isso é opcional; confirme com o usuário antes de implementar.
- **Testes da ponte Laravel** com **Orchestra Testbench** + Pest: registro do provider, merge e publicação da configuração, resolução pelo container e funcionamento da Facade.
- O **README** deve ter uma seção específica de Laravel: instalação, `vendor:publish`, `.env`, uso por injeção de dependência e pela Facade, e uso dentro de um Job.

### 5.4. Fora do escopo

- SOAP.
- **Logística Reversa** (Autorização de Postagem, coleta domiciliar e venda de embalagens), porque as etiquetas de reversa já chegam prontas ao `marcelo-envios` pela API dos Correios. Não implemente os campos `Remetente`, `TipoAutorizacaoReversa`, `DiasPrazoPostagemEticket` nem `DataColeta`.
- Interface do `marcelo-envios`.
- Geração de etiquetas na API dos Correios.

---

## 6. Testes

### 6.1. Pest (automatizados)

- **Unitários:** normalizadores, validadores (incluindo o dígito verificador da etiqueta, CPF/CNPJ, CEP, UF, celular, chave de 44 dígitos e os limites da DCe), serialização do payload e interpretação das respostas de sucesso e erro.
- **Cliente:** use um cliente PSR-18 simulado (ex.: `php-http/mock-client`) com fixtures JSON baseadas nos exemplos do PDF e, depois, nas respostas reais de homologação.
- **Integração (opt-in):** testes contra a homologação, num grupo separado (ex.: `->group('integration')`), que só rodam quando as variáveis de ambiente das credenciais existirem. **Esses testes criam postagens reais no ambiente de homologação**, então não devem rodar por padrão.

### 6.2. Postman

Crie, versionada no repositório (ex.: `postman/`):

- uma **coleção** com requisições de postagem válida, postagem com etiqueta informada, e postagem inválida (para conferir a `ListaErros`);
- um **environment** de homologação com as variáveis `baseUrl`, `usuario`, `token` e `idPerfil`;
- testes (scripts) que validem o status HTTP e o `StatusPostagem`.

Use a coleção também para investigar as inconsistências da seção 4.6. Documente como rodá-la pelo Postman CLI.

---

## 7. Documentação do projeto

| Arquivo | Conteúdo |
|---|---|
| `README.md` | Instalação via Composer, requisitos, configuração, **exemplos de uso** (postagem normal, postagem com etiqueta existente, tratamento de erros), **seção de uso no Laravel**, como rodar os testes e a coleção Postman. |
| `CLAUDE.md` (raiz, versionado) | Stack, estrutura de pastas, convenções (nomes em português, estilo de código, padrão de commits), comandos (instalar, testar, analisar e formatar), regras do projeto e orientações para agentes LLM. Inclua a instrução de **ler o `docs/PROGRESSO.md` no início de cada sessão e atualizá-lo ao final**. |
| `docs/PROGRESSO.md` | Funcionalidades implementadas, tarefas pendentes, **decisões tomadas e seus motivos**, resultados das validações em homologação (seção 4.6) e o próximo passo recomendado. |

---

## 8. Continuidade entre sessões e máquinas

O projeto será desenvolvido em máquinas e sessões diferentes. Por isso:

1. Use **Git** desde o início. O diretório ainda não é um repositório.
2. Faça commits pequenos e coesos, um por etapa concluída.
3. Atualize o **`docs/PROGRESSO.md`** em todo commit relevante.
4. Mantenha o **`CLAUDE.md`** sempre de acordo com o estado real do projeto.
5. Nunca versione credenciais. Inclua um `.gitignore` e um `.env.example` (ou equivalente), com valores fictícios ou com as credenciais públicas de homologação, devidamente identificadas.

---

## 9. Plano de execução sugerido

1. **Leitura:** ler o PDF por completo e este prompt. Listar ao usuário as dúvidas que ainda restarem (seção 10).
2. **Fundação:** `git init`, `composer.json`, estrutura de pastas, `.gitignore`, `CLAUDE.md`, `docs/PROGRESSO.md` e Pest configurado.
3. **Investigação:** montar a coleção Postman e resolver as inconsistências da seção 4.6 em homologação. Registrar os resultados.
4. **Modelos:** DTOs/objetos de valor do payload (PerfilVipp, ContratoEct, Destinatario, Servico, NotaFiscal, Volume, DeclaracaoConteudo, ItemConteudo) e enums (ex.: serviços adicionais).
5. **Normalização e validação:** com testes.
6. **Transporte e cliente:** integração PSR-18/17, montagem da requisição e tratamento dos códigos HTTP.
7. **Resposta e exceções:** objeto de resultado, `ListaErros` e hierarquia de exceções.
8. **Ponte Laravel:** Service Provider, configuração, Facade e testes com Testbench.
9. **Testes de integração** (opt-in).
10. **README** com exemplos (PHP puro e Laravel).
11. **Preparação para o Packagist:** metadados do `composer.json`, licença e versionamento semântico. **Não publique no Packagist nem no GitHub sem a autorização explícita do usuário.**

Ao fim de cada etapa: testes passando, `PROGRESSO.md` atualizado e commit feito.

---

## 10. Pontos em aberto (perguntar ao usuário quando chegar a hora)

- **Versões do Laravel** suportadas (ex.: só a versão usada pelo `marcelo-envios` ou as duas últimas versões principais). Verificar a versão do Laravel no `marcelo-envios`.
- **Eventos Laravel** na ponte (seção 5.3): implementar ou não.
- **Licença** do pacote (ex.: MIT ou proprietária).
- **Namespace** raiz: confirmar `MarceloFecchio\Vipp`.
- **Ferramentas de qualidade:** análise estática (ex.: PHPStan e nível) e formatação (ex.: PHP-CS-Fixer com PER-CS ou Laravel Pint).
- **Padrão de commits** (ex.: Conventional Commits).
- **CI** (ex.: GitHub Actions rodando testes e análise).
- **Política de retry** para os códigos `429`, `504` e `509`: o SDK deve tentar de novo automaticamente ou deixar isso para o consumidor?
- **Envio em lote:** o endpoint recebe uma postagem por requisição. Confirmar se o SDK deve oferecer algum utilitário para várias postagens ou se isso fica no `marcelo-envios`.
- Os itens da seção 4.6 que não puderem ser resolvidos em homologação devem ser levados ao suporte da Visual Set (`comercial@visualset.com.br`), com a ajuda do usuário.

---

## 11. Regras gerais para o agente

- Preserve os requisitos deste documento. **Não assuma requisitos importantes** que não estejam aqui. Em caso de dúvida que afete a arquitetura ou o comportamento do SDK, **pergunte**.
- A documentação oficial do ViPP prevalece sobre este resumo. Se encontrar divergência, registre-a no `PROGRESSO.md`.
- Ações externas ou irreversíveis (publicar no Packagist, criar repositório remoto, fazer push) exigem confirmação do usuário.
- Escreva código, comentários e documentação em português, mantendo nomes coerentes com os campos do ViPP.
