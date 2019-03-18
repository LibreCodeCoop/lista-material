# Consulta Material de universidades

Consulta Material de universidades.

URL de início da consulta:

```
https://www.suryadental.com.br/academico
```

A execução do projeto com Docker é bem simples:

```bash
git clone https://github.com/lyseontech/consulta-material
cd consulta-material
docker-compose up -d
docker-compose exec php7 bin/consulta-material <lista-de-cnpj>
```

Onde:

| Campo             |  Descrição                         |
|-------------------|------------------------------------|
| `<lista-de-cnpj>` | Lista de CNPJ separada por vírgula |
