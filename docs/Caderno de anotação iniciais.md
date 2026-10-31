# Caderno de Anotações — SDK PHP para integração com ViPP

## 1. Objetivo deste arquivo

Este arquivo funciona como um **caderno de anotações e requisitos** para o projeto.

As informações registradas aqui deverão ser utilizadas posteriormente por um agente de IA / LLM para gerar o arquivo:

`/docs/PROMPT-INICIAL.md`

Esse arquivo deverá reunir, de forma estruturada, todas as instruções necessárias para que outro agente LLM possa posteriormente desenvolver a aplicação proposta.

> **Importante:** nesta etapa, **não deve ser criada nenhuma aplicação, implementação ou código-fonte**.  
> O objetivo é exclusivamente organizar os requisitos e, posteriormente, gerar um prompt completo para orientar o desenvolvimento do projeto.

---

## 2. Objetivo do projeto

Criar um **componente / SDK em PHP 8.4** para integração com a API do sistema **ViPP**.

O componente deverá ser desenvolvido de forma independente e reutilizável, permitindo posteriormente sua publicação e instalação por meio do **Packagist**.

Nome previsto para o projeto no GitHub e no Packagist:

`marcelofecchio/vipp-php-sdk`

---

## 3. Contexto da integração

O sistema **ViPP** é utilizado em algumas agências franqueadas dos Correios.

Ele permite que clientes dessas agências enviem informações relacionadas às etiquetas geradas pela API dos Correios para o sistema da agência.

Essa integração tem como objetivo facilitar e agilizar os processos operacionais e logísticos realizados pela agência.

---

## 4. Utilização do SDK

O SDK será utilizado posteriormente pelo sistema de envios chamado:

`marcelo-envios`

Esse sistema já foi desenvolvido.

Em uma etapa futura, será adicionada ao `marcelo-envios` uma interface que permitirá exportar para o sistema ViPP os dados das etiquetas geradas.

O SDK desenvolvido neste projeto será responsável por realizar a comunicação entre o `marcelo-envios` e o sistema ViPP.

---

## 5. Documentação de referência

Antes de definir a estrutura e os recursos do SDK, deverá ser analisada cuidadosamente a documentação oficial da integração ViPP disponível em:

[https://www.visualset.com.br/layouts/WebServicePostagemViPP.pdf](https://www.visualset.com.br/layouts/WebServicePostagemViPP.pdf)

A implementação futura deverá ser baseada nas informações e especificações apresentadas nessa documentação.

---

## 6. Documentação do projeto

O projeto deverá possuir documentação suficiente para facilitar sua utilização, manutenção e continuidade do desenvolvimento.

### 6.1. README.md

Criar um arquivo:

`README.md`

O documento deverá apresentar as informações necessárias para utilização do SDK, incluindo **exemplos de uso**.

---

### 6.2. CLAUDE.md

Criar e versionar na raiz do projeto o arquivo:

`CLAUDE.md`

Esse arquivo deverá centralizar informações importantes para agentes LLM que venham a trabalhar no projeto, incluindo:

- stack utilizada;
    
- convenções adotadas;
    
- comandos relevantes;
    
- regras do projeto;
    
- orientações necessárias para manutenção e desenvolvimento.
    

---

### 6.3. PROGRESSO.md

Criar o arquivo:

`docs/PROGRESSO.md`

Esse documento deverá registrar continuamente:

- funcionalidades já implementadas;
    
- tarefas ainda pendentes;
    
- decisões tomadas durante o desenvolvimento;
    
- informações relevantes para continuidade do projeto.
    

A finalidade é permitir que o desenvolvimento possa ser retomado com facilidade em outra máquina, ambiente ou sessão de um agente LLM.

---

## 7. Estratégia para continuidade do desenvolvimento

O projeto deverá utilizar em conjunto:

- **Git**, para versionamento;
    
- **CLAUDE.md**, para registrar contexto, regras e convenções;
    
- **docs/PROGRESSO.md**, para acompanhar o andamento e as decisões do projeto.
    

Essa estrutura deverá facilitar o trabalho no mesmo projeto utilizando máquinas diferentes ou sessões diferentes de agentes LLM, reduzindo a perda de contexto entre as sessões.

---

## 8. Testes automatizados

O projeto deverá possuir testes automatizados.

Utilizar uma das seguintes ferramentas:

- **Pest**
    
- **PHPUnit**
    

A escolha entre elas poderá ser definida durante o planejamento ou desenvolvimento do projeto.

---

## 9. Testes da API com Postman

Também deverá ser criada uma coleção ou estrutura de testes para utilização no **Postman**.

O objetivo será permitir testar de forma prática as operações relacionadas à integração com o sistema ViPP.

---

## 10. Interação com o usuário durante o planejamento

Caso existam informações insuficientes, decisões importantes ainda não definidas ou dúvidas que possam alterar a arquitetura ou o funcionamento do SDK, o agente poderá fazer perguntas antes de prosseguir.

As perguntas deverão ter como objetivo esclarecer requisitos relevantes para a correta definição do projeto.

---

## 11. Regra principal desta etapa

O conteúdo deste arquivo representa **anotações e requisitos preliminares**.

Ao utilizá-lo para gerar o futuro `/docs/PROMPT-INICIAL.md`, o agente deverá:

- organizar as informações de forma técnica e estruturada;
    
- preservar os requisitos definidos neste documento;
    
- não assumir requisitos importantes que não estejam especificados;
    
- identificar pontos que precisem de esclarecimento;
    
- preparar instruções suficientemente claras para que outro agente LLM possa desenvolver posteriormente o projeto.
    

**Não desenvolver o SDK nem gerar código nesta etapa.**