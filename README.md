# vipp-php-sdk

SDK PHP 8.4 para o **Web Service de Postagem do ViPP** (VisualSet), o sistema usado por agências franqueadas dos Correios (AGF) para receber os dados das etiquetas dos clientes.

- REST/JSON, cliente HTTP **PSR-18** (Guzzle por padrão).
- DTOs imutáveis em português, espelhando os campos do ViPP.
- **Validação local** das regras do manual antes do envio, com mensagens por campo.
- Resultado tipado: etiqueta, valores, prazo e erros do ViPP.
- **Integração com Laravel** (auto-discovery, `config/vipp.php`, Facade `Vipp`, `Vipp::fake()`).
- Comportamento conferido na homologação real; veja [o que difere do PDF](#o-que-difere-do-manual-do-vipp).

Fora do escopo: SOAP e Logística Reversa.

## Requisitos

- PHP **8.4+** com `ext-json`.
- Um cliente PSR-18. O Laravel já traz o Guzzle; fora dele, `composer require guzzlehttp/guzzle`.
- Laravel **12 ou 13** (opcional).

## Instalação

```bash
composer require marcelofecchio/vipp-php-sdk
```

## Uso em PHP puro

```php
use MarceloFecchio\Vipp\Config\PerfilVipp;
use MarceloFecchio\Vipp\Config\VippConfig;
use MarceloFecchio\Vipp\Enum\ServicoAdicional;
use MarceloFecchio\Vipp\Postagem\{ContratoEct, DeclaracaoConteudo, Destinatario, ItemConteudo, Postagem, Volume};
use MarceloFecchio\Vipp\VippClient;

$vipp = new VippClient(new VippConfig(new PerfilVipp('usuario', 'token', '9363'), exigirContrato: true));
// Homologação com as credenciais públicas do manual: VippClient::homologacao()

$postagem = new Postagem(
    destinatario: new Destinatario(
        nome: 'Maria da Silva',
        endereco: 'Rua Conselheiro Nébias',
        numero: '263',
        bairro: 'Campos Elíseos',
        cidade: 'São Paulo',
        uf: 'SP',
        cep: '01203-001',              // formatação é removida
        celular: '(11) 93737-8999',
    ),
    volume: new Volume(
        declaracaoConteudo: new DeclaracaoConteudo(
            documentoRemetente: '03.998.581/0001-90',
            documentoDestinatario: '529.982.247-25',
            itens: [new ItemConteudo('Camiseta de algodão', quantidade: 2, valor: '62,99')], // valor UNITÁRIO
        ),
        peso: 350,                       // gramas (int)
        altura: 4, largura: 11, comprimento: 16, // cm (int)
        etiqueta: 'SQ000352886BR',       // etiqueta já gerada nos Correios (opcional)
        codigoBarraVolume: 'PEDIDO-1234',// sua referência interna, indexada no ViPP
        valorDeclarado: 150,             // reais
        adicionais: [ServicoAdicional::AvisoRecebimento],
    ),
    servicoEct: '03298',                 // PAC; opcional se o perfil já define
    contratoEct: new ContratoEct(        // na prática, obrigatório em produção (veja abaixo)
        nrContrato: '9912345678',
        nrCartao: '0067345678',
    ),
);

$resultado = $vipp->postarObjeto($postagem);

if ($resultado->ehValida()) {
    $resultado->etiqueta();                         // "SQ000352886BR"
    $resultado->volume->valorPostagem->formatar();  // "28,47"
    $resultado->volume->diasUteisPrazo;             // 3
    $resultado->etiquetaDivergente();               // true se o ViPP registrou outra etiqueta
} else {
    $resultado->mensagensDeErro();                  // ["[1330] Cep: É Obrigatório Informar o CEP do Destinatário!"]
}
```

Um exemplo executável está em [examples/postar-objeto.php](examples/postar-objeto.php).

### Etiqueta já gerada nos Correios

Quando o `Volume` recebe `etiqueta`, o SDK confere o formato (`AA123456785BR`) e o dígito verificador antes de enviar. Depois, **sempre confira `etiquetaDivergente()`**: na homologação o perfil 9363 ignora a etiqueta enviada e gera outra. Em produção, o comportamento depende da configuração do Perfil de Importação feita pela AGF e **ainda não foi confirmado**. Guarde a etiqueta devolvida por `etiqueta()` e avise o operador quando houver divergência.

### Contrato e cartão de postagem

O manual diz que `ContratoEct` só é necessário quando o Perfil de Importação não tem o contrato configurado (Nota 1), e a homologação aceita postagens sem ele. **Em produção, porém, um perfil recusou a postagem sem `NrContrato` e `NrCartao`** (observado em 11/11/2026). A resposta foi HTTP 400 com:

```
[91] NumeroContrato: Erro sem descrição
[91] NumeroCartao: Erro sem descrição
```

O ViPP manda o erro 91 com a descrição **vazia**. "Erro sem descrição" é o texto que o SDK coloca nesse caso, então só o nome do campo indica o problema. Com o mesmo payload acrescido de `ContratoEct(nrContrato: …, nrCartao: …)`, a postagem foi aceita.

O ViPP manda o erro 91 sem descrição. O SDK preenche a mensagem com "obrigatório neste perfil (erro 91 do ViPP, sem descrição); informe ContratoEct com NrContrato e NrCartao." e marca `ErroVipp::$semDescricao`. Para detectar o caso no código, use `$resultado->exigeContrato()`.

Recomendações:

- Em produção, **sempre informe `ContratoEct` com `nrContrato` e `nrCartao`**, usando o contrato dos Correios que gerou a etiqueta.
- **Ligue `exigirContrato`** (`new VippConfig(..., exigirContrato: true)` ou `VIPP_EXIGIR_CONTRATO=true`). A validação local passa a barrar, antes do envio, a postagem sem contrato e cartão, com mensagem clara. O padrão é `false` porque o manual trata o contrato como opcional e a homologação não o exige.
- Envie só dígitos, mantendo os zeros à esquerda do cadastro dos Correios. O cartão com 10 dígitos e zeros à esquerda foi aceito.
- Não envie o cartão sem o contrato: a homologação responde com o erro 1100 ("O Número do Cartão É Obrigatóriamente Numérico!"), mesmo o cartão sendo numérico. A validação local barra esse caso sempre.
- `CodigoAdministrativo` não foi exigido nesse perfil.

Ainda não confirmado: se o cartão é aceito sem os zeros à esquerda e se algum perfil exige `CodigoAdministrativo`.

### Descrição do conteúdo

A validação local só aceita, em `DescricaoConteudo`, letras (com acento), dígitos, espaço e `. , - / ( ) % : ; +` (`ItemConteudo::CARACTERES_PERMITIDOS`), com 3 a 70 caracteres. A SEFAZ recusa emoji e símbolos e cancela a DC-e depois. Descrições vindas de NF-e costumam trazer `#`, `&`, aspas e `*`, e uma única descrição assim reprova a postagem inteira. Limpe a descrição com `ItemConteudo::limparDescricao()`:

```php
new ItemConteudo(ItemConteudo::limparDescricao($descricaoDaNota), $quantidade, $valorUnitario);
// 'CAMISETA #12 & "PREMIUM" * ALGODÃO' → 'CAMISETA 12 PREMIUM ALGODÃO'
```

A função troca os caracteres não aceitos por espaço, junta espaços repetidos e corta em 70 caracteres. Se sobrarem menos de 3 caracteres, a validação continua recusando: use uma descrição genérica do produto.

### Valores, peso e datas

| Dado | Aceita | Enviado como |
|---|---|---|
| Dinheiro | `int`/`float` em reais, `"1.234,56"`, `"1234.56"`, `Dinheiro::centavos(123456)` | `"1234,56"` |
| Peso / medidas | `int` (gramas / cm) | `"1600"` |
| Data da NF | `DateTimeInterface`, `"15/07/2026"`, `"2026-07-15"` | `"15/07/2026"` |
| CEP, telefones, CPF/CNPJ | com ou sem pontuação | só dígitos (CNPJ alfanumérico mantém as letras) |

## Tratamento de erros

| Situação | O que acontece |
|---|---|
| Dados reprovados na validação local | `ValidacaoException` com `$e->violacoes` (campo + mensagem). **Nada é enviado.** |
| ViPP recusa os dados (HTTP 400) | **Não é exceção**: `ResultadoPostagem` com `ehValida() === false` e `erros`. |
| Usuário, token ou perfil inválidos (HTTP 401) | `AutenticacaoException` com `$e->erros` (1030, 1002). |
| Outros HTTP (404, 429, 5xx…) | `HttpException`; `$e->ehTemporario()` indica 429/5xx. |
| Rede / timeout | `TransporteException`. |
| Resposta fora do formato | `RespostaInvalidaException`. |

Todas implementam `MarceloFecchio\Vipp\Exceptions\VippException`.

**O SDK não repete requisições automaticamente.** A postagem não é idempotente, e um reenvio após timeout pode duplicar o objeto. Antes de reenviar, consulte o ViPP (pelo `CodigoBarraVolume`, que é indexado).

Para validar sem enviar nada, use `$vipp->validar($postagem)`, que devolve uma lista de `Violacao`.

### Códigos de erro observados

| Código | Campo | Significado |
|---|---|---|
| 91 | `NumeroContrato`, `NumeroCartao` | Contrato ou cartão obrigatório neste perfil. Vem **sem descrição** (visto em produção); `$resultado->exigeContrato()` |
| 1100 | `NrCartao` | Cartão enviado sem o contrato (a mensagem diz "não numérico") |
| 1000, 1010, 1020 | `Usuario`, `Token`, `IdPerfil` | Credencial não informada |
| 1002 | `IdPerfil` | Usuário sem acesso ao perfil, ou perfil inexistente (HTTP 401) |
| 1030 | `Login` | Usuário ou token inválidos (HTTP 401) |
| 1310, 1320, 1330 | `Nome`, `Endereco`, `Cep` | Dado obrigatório do destinatário ausente |
| 1500 | `DtNotaFiscal` | Data fora do formato `DD/MM/AAAA` |
| 2002 | `Etiqueta` | Dígito verificador inválido |
| 2200 | `DescricaoConteudo` | Descrição do item ausente ou com menos de 3 caracteres |
| 2222 | `Valor` | Valor do item não numérico |

### Testar as credenciais

**O ViPP não tem endpoint de consulta nem de login**: o único método é `PostarObjeto`. Por isso não existe como "testar a conexão" sem criar uma postagem real na agência. `validar()` é só local: confere os dados da postagem, mas não usuário, token nem perfil.

Na prática, a primeira postagem confirma as credenciais. Um HTTP 401 vira `AutenticacaoException`, com o erro 1030 (usuário ou token) ou 1002 (perfil). Se a sua aplicação tem uma tela de credenciais, explique isso ao usuário em vez de oferecer um botão "Testar conexão".

## Laravel

O Service Provider e a Facade são registrados por auto-discovery.

```bash
php artisan vendor:publish --tag=vipp-config   # opcional: cria config/vipp.php
```

```dotenv
VIPP_USUARIO=...
VIPP_TOKEN=...
VIPP_ID_PERFIL=...
# opcionais
VIPP_BASE_URL=https://vpsrv.visualset.com.br
VIPP_TIMEOUT=30
VIPP_VALIDAR_ANTES_DE_ENVIAR=true
VIPP_EXIGIR_CONTRATO=true      # recomendado em produção; padrão false
```

As credenciais só são lidas quando o cliente é usado pela primeira vez, então a aplicação sobe normalmente sem elas.

### Credenciais vindas do banco

Se as credenciais são cadastradas numa tela da aplicação, e não no `.env`, registre o seu próprio vínculo de `VippClientInterface` num Service Provider da aplicação. Ele substitui o vínculo do pacote:

```php
use MarceloFecchio\Vipp\Config\{PerfilVipp, VippConfig};
use MarceloFecchio\Vipp\Exceptions\ConfiguracaoException;
use MarceloFecchio\Vipp\{VippClient, VippClientInterface};

public function register(): void
{
    // bind (e não singleton): credenciais alteradas na tela valem sem reiniciar filas nem o Octane
    $this->app->bind(VippClientInterface::class, function (): VippClientInterface {
        $credenciais = Configuracoes::vipp() ?? throw new ConfiguracaoException('Cadastre as credenciais do ViPP.');

        return new VippClient(new VippConfig(
            new PerfilVipp($credenciais->usuario, $credenciais->token, $credenciais->idPerfil),
            exigirContrato: true,
        ));
    });
}
```

- Use `bind` quando as credenciais puderem mudar com a aplicação no ar. Com `singleton`, workers de fila e o Octane continuariam usando as credenciais antigas até reiniciar.
- `Vipp::fake()` continua funcionando, porque troca o vínculo de `VippClientInterface` qualquer que seja a origem.
- Dependa sempre de `VippClientInterface`. A classe concreta `VippClient` continua ligada ao `config/vipp.php` do pacote.
- Guarde o token criptografado (ex.: cast `encrypted` do Eloquent).

### Injeção de dependência (recomendado)

Dependa da **interface**, para que `Vipp::fake()` funcione nos testes:

```php
use MarceloFecchio\Vipp\VippClientInterface;

final class ExportarEtiquetaParaVipp
{
    public function __construct(private VippClientInterface $vipp) {}

    public function __invoke(Envio $envio): void
    {
        $resultado = $this->vipp->postarObjeto(PostagemFactory::de($envio));
        // ...
    }
}
```

### Facade

```php
use MarceloFecchio\Vipp\Laravel\Facades\Vipp;

$resultado = Vipp::postarObjeto($postagem);
$violacoes = Vipp::validar($postagem);
```

### Em um Job

```php
final class ExportarParaVippJob implements ShouldQueue
{
    use Queueable;

    public $tries = 1; // não repetir automaticamente: a postagem não é idempotente

    public function __construct(public int $envioId) {}

    public function handle(VippClientInterface $vipp): void
    {
        $envio = Envio::findOrFail($this->envioId);
        $resultado = $vipp->postarObjeto(PostagemFactory::de($envio));

        if (!$resultado->ehValida()) {
            Log::warning('ViPP recusou a postagem', ['envio' => $envio->id, 'erros' => $resultado->mensagensDeErro()]);
            return;
        }

        $envio->update(['etiqueta_vipp' => $resultado->etiqueta()]);
    }
}
```

O cliente não guarda estado entre chamadas e pode ser usado como singleton em filas e no Octane. O token nunca aparece em `var_dump`, nas mensagens de exceção nem em `$resultado->resposta`.

### Testes na aplicação

```php
use MarceloFecchio\Vipp\Laravel\Facades\Vipp;
use MarceloFecchio\Vipp\Testing\VippFake;

$fake = Vipp::fake();                       // troca a Facade E a injeção de VippClientInterface
$fake->responderCom(
    VippFake::resultadoInvalido([1330 => 'É Obrigatório Informar o CEP do Destinatário!']),
);

// ... executa o código

expect($fake->postagens())->toHaveCount(1);
expect($fake->ultimaPostagem()->volume->etiqueta)->toBe('SQ000352886BR');
```

Sem resposta programada, o fake devolve uma postagem válida, repetindo a etiqueta enviada ou gerando uma etiqueta válida. Ele aplica a mesma validação local do cliente real. Se a aplicação usa `exigirContrato`, chame `Vipp::fake(exigirContrato: true)`. Fora do Laravel, use `new VippFake()`.

## O que difere do manual do ViPP

Conferido na homologação em 30/10/2026 e em produção (integração do MARCELO ENVIOS) em 11/11/2026. Detalhes em [docs/PROGRESSO.md](docs/PROGRESSO.md).

- **Em produção, `ContratoEct` (`NrContrato` e `NrCartao`) foi obrigatório**, mesmo o manual dizendo que o perfil pode dispensá-lo. Sem ele, o ViPP devolve o erro 91 com a descrição vazia. Use `exigirContrato` (veja [Contrato e cartão de postagem](#contrato-e-cartão-de-postagem)).
- **Cartão sem contrato dá o erro 1100**, com uma mensagem enganosa ("não numérico").
- **Datas** precisam de barras (`DD/MM/AAAA`). O formato sem separador citado na Nota 8 é recusado com o erro 1500.
- **Valores sem separador são lidos como centavos** (`"10"` = R$ 0,10). Por isso o SDK sempre envia `"10,00"`.
- **Peso com vírgula é truncado** (`"1,6"` = 1 g). Por isso o SDK só aceita gramas inteiros.
- **Só o primeiro volume da lista é processado**: os demais são descartados sem aviso. Por isso o SDK envia um volume por postagem.
- **Etiqueta informada pode ser substituída**: acontece na homologação; em produção, ainda não foi confirmado (veja `etiquetaDivergente()`).
- **Não há endpoint para testar as credenciais**: só `PostarObjeto` (veja [Testar as credenciais](#testar-as-credenciais)).
- **HTTP 400** para erros de validação e **401** para credenciais ou perfil inválidos. O manual não deixa isso claro.
- A homologação aceita dados que os Correios e a SEFAZ recusam depois, como emoji na descrição, item acima de R$ 100 mil e CPF inválido. A validação local do SDK bloqueia esses casos.

## Desenvolvimento

```bash
composer install
composer qa                      # estilo + PHPStan (nível max) + testes
composer test                    # unitários e Laravel
composer cs:fix
VIPP_RUN_INTEGRATION=1 composer test:integration   # cria postagens reais na homologação
php postman/gerar-colecao.php    # regenera a coleção Postman
postman collection run postman/ViPP.postman_collection.json -e postman/Homologacao.postman_environment.json
```

Veja [postman/README.md](postman/README.md), [CLAUDE.md](CLAUDE.md) e [docs/PROGRESSO.md](docs/PROGRESSO.md).

## Licença

[MIT](LICENSE).
