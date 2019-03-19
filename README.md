# Consulta Material de universidades

Consulta Material de universidades.

URL de início da consulta:

```
https://www.suryadental.com.br/academico
```

# Iniciando serviço com Docker

A execução do projeto com Docker é bem simples:

```bash
git clone https://github.com/lyseontech/consulta-material
cd consulta-material
docker-compose up -d
docker-compose exec php7 bash
```

Após acessar o container, você terá duas opções, ou rodar o comando de
importação sem argumentos, o que irá fazer com que sejam importados os dados de
todos os estados ao mesmo tempo ou passar o argumento `--uf` para dizer de qual
estado irá importar.

## Importando para estados específicos

Passando para estados específicos, o programa de importação só irá liberar o
terminal após conclusão da importação.

Para inicar a importação de apenas um estado, execute o seguinte comando:

```bash
php bib/consulta-material --uf=ac
```

Para inicar a importação de mais de um estado, execute o seguinte comando:


```bash
php bib/consulta-material --uf=ac --uf=am
```

## Importando todos os estados

Sem passar estados (o que importa todos os estados) ou passando mais de um
estado, o programa irá liberar o terminal imediatamente e irá iniciar um
processo para cada estado em background.

Para inicar a importação, execute o seguinte comando:

```bash
php bin/consulta-material
```

## Arquivo de log

Após conclusão da importação terá um arquivo chamado log com a sigla da UF e a
hora que a importação concluiu.
