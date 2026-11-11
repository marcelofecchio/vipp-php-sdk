# PROGRESSO — vipp-php-sdk

## Estado atual

**v0.1.0 lançada em 11/11/2026** (tag `v0.1.0` e release no GitHub; cadastro no Packagist em andamento, ver "Pontos em aberto"). Todos os requisitos de `docs/PROMPT-INICIAL.md` estão implementados. `composer qa` passa, com 111 testes unitários/Laravel. Os testes de integração e a coleção Postman (20 de 20 asserções) passam contra a homologação.

**Próximo passo recomendado:** confirmar com a AGF/VisualSet como o perfil de produção trata a etiqueta enviada (ver "Pontos em aberto"). Depois, integrar no `marcelo-envios`.

## Histórico

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

- [ ] **TODO(confirmar)** com a AGF/VisualSet: o perfil de **produção** mantém a etiqueta enviada (Nota 16) ou também gera outra? É essencial para o `marcelo-envios`.
- [ ] Confirmar que o servidor de produção é o mesmo `vpsrv.visualset.com.br`, só com outras credenciais.
- [ ] Conjunto exato de caracteres aceitos pela SEFAZ na descrição da DC-e.
- [ ] Eventos Laravel (postagem enviada, válida ou inválida)? Decidir com o usuário.
- [x] Tag `v0.1.0` e release no GitHub (11/11/2026).
- [ ] Cadastro no Packagist (`marcelofecchio/vipp-php-sdk`) e webhook de atualização automática; depois, testar `composer require marcelofecchio/vipp-php-sdk` em um projeto limpo.
- [ ] Integração no `marcelo-envios`: montar a `Postagem` a partir do envio e da etiqueta dos Correios, e definir a política de reenvio.
