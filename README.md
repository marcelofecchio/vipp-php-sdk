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
use MarceloFecchio\Vipp\Postagem\{DeclaracaoConteudo, Destinatario, ItemConteudo, Postagem, Volume};
use MarceloFecchio\Vipp\VippClient;

$vipp = new VippClient(new VippConfig(new PerfilVipp('usuario', 'token', '9363')));
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

Quando o `Volume` recebe `etiqueta`, o SDK confere o formato (`AA123456785BR`) e o dígito verificador antes de enviar. Depois, **sempre confira `etiquetaDivergente()`**: na homologação o perfil 9363 ignora a etiqueta enviada e gera outra. Em produção, o comportamento depende da configuração do Perfil de Importação feita pela AGF.

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
```

As credenciais só são lidas quando o cliente é usado pela primeira vez, então a aplicação sobe normalmente sem elas.

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

Sem resposta programada, o fake devolve uma postagem válida, repetindo a etiqueta enviada ou gerando uma etiqueta válida. Ele aplica a mesma validação local do cliente real. Fora do Laravel, use `new VippFake()`.

## O que difere do manual do ViPP

Conferido na homologação em 30/10/2026; detalhes em [docs/PROGRESSO.md](docs/PROGRESSO.md).

- **Datas** precisam de barras (`DD/MM/AAAA`). O formato sem separador citado na Nota 8 é recusado com o erro 1500.
- **Valores sem separador são lidos como centavos** (`"10"` = R$ 0,10). Por isso o SDK sempre envia `"10,00"`.
- **Peso com vírgula é truncado** (`"1,6"` = 1 g). Por isso o SDK só aceita gramas inteiros.
- **Só o primeiro volume da lista é processado**: os demais são descartados sem aviso. Por isso o SDK envia um volume por postagem.
- **Etiqueta informada pode ser substituída** (veja `etiquetaDivergente()`).
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
