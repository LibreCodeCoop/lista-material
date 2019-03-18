<?php

namespace ListaMaterial\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;

class ConsultaCommand extends Command
{
    /**
     * @var Client
     */
    private $client;
    private $output = [];
    private $estados = [
        'AC',
        'AL',
        'AM',
        'AP',
        'BA',
        'CE',
        'DF',
        'ES',
        'GO',
        'MA',
        'MG',
        'MS',
        'MT',
        'PA',
        'PB',
        'PE',
        'PI',
        'PR',
        'RJ',
        'RO',
        'RR',
        'RS',
        'SC',
        'SE',
        'SP',
        'TO'
    ];
    /**
     * @var Crawler
     */
    private $crawlerListaMaterial;
    private $fileHandle;
    protected function configure()
    {
        $this
            ->setName('consulta')
            ->setDescription('Realiza consulta de material escolar.')
            ->setHelp(<<<HELP
                O comando <info>consulta</info> realiza consulta de empresa.
                HELP
            )
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->client = new Client();
        $this->getData();
        $output->write(json_encode($this->output));
    }

    private function getData()
    {
        $this->fileHandle = fopen('materiais-'.date('ymdhis').'.csv', 'w');
        fputcsv($this->fileHandle, [
            'img',
            'titulo',
            'descricao',
            'disponivel',
            'codigo-item',
            'marca',
            'estado',
            'cidade',
            'instituicao',
            'periodo',
            'lista-nome',
            'lista-total',
            'lista-codigo'
        ]);
        foreach($this->estados as $estado) {
            $cidades = $this->getCidade($estado);
            foreach($cidades as $cidade) {
                $instituicoes = $this->getInstituicao($estado, $cidade);
                foreach($instituicoes as $instituicao) {
                    $periodos = $this->getPeriodo($estado, $cidade, $instituicao);
                    foreach ($periodos as $periodo) {
                        $listas = $this->getListaMaterial($estado, $cidade, $instituicao, $periodo);
                        foreach($listas as $lista) {
                            $this->writeToCsv(
                                $estado,
                                $cidade,
                                $instituicao,
                                $periodo,
                                $lista,
                                $this->getItens($lista['codigo'])
                            );
                        }
                    }
                }
            }
        }
        fclose($this->fileHandle);
    }

    private function getCidade($estado)
    {
        $crawler = $this->client->request('GET',
            'https://www.suryadental.com.br/academico/index/getCidade/?estado='.$estado
        );
        $cidades = $crawler->filter('option')->each(function ($node) {
            return $node->attr('value');
        });
        return array_filter($cidades, 'strlen');
    }

    private function getInstituicao($estado, $cidade)
    {
        $crawler = $this->client->request('GET',
            'https://www.suryadental.com.br/academico/index/getInstituicao/?'.
            http_build_query([
                'cidade' => $cidade,
                'estado' => $estado
            ])
        );
        $cidades = $crawler->filter('option')->each(function ($node) {
            return $node->attr('value');
        });
        return array_filter($cidades, 'strlen');
    }

    private function getPeriodo($estado, $cidade, $instituicao)
    {
        $crawler = $this->client->request('GET',
            'https://www.suryadental.com.br/academico/index/getPeriodo/?'.
            http_build_query([
                'instituicao' => $instituicao,
                'cidade' => $cidade,
                'estado' => $estado
            ])
        );
        $instituicoes = $crawler->filter('option')->each(function ($node) {
            return $node->attr('value');
        });
        return array_filter($instituicoes, 'strlen');
    }

    private function getListaMaterial($estado, $cidade, $instituicao, $periodo)
    {
        $this->crawlerListaMaterial = $this->client->request('POST',
            'https://www.suryadental.com.br/academico/lista/',
            [
                'lista_estado' => $estado,
                'lista_cidade' => $cidade,
                'lista_instituicao' => $instituicao,
                'lista_periodo' => $periodo
            ]
        );
        $listas = $this->crawlerListaMaterial->filter('li.col-xs-12')->each(function (Crawler $node) {
            $descricaoLista = $node->filter('div')->each(function (Crawler $node, $i) {
                if($i == 0) {
                    return $node->text();
                } elseif($i == 1) {
                    return $node->children('span')->text();
                } elseif($i == 3) {
                    $button = $node->children('button');
                    return substr($button->attr('data-target'), 6);
                }
            });
            return [
                'nome'   => $descricaoLista[0],
                'total'  => $descricaoLista[1],
                'codigo' => $descricaoLista[3]
            ];
        });
        return $listas;
    }
    
    private function getItens($codigo)
    {
        $Lista = $this->crawlerListaMaterial->filter('#item-'.$codigo);
        return $Lista->filter('.tbody li')->each(function (Crawler $node) {
            $item = [
                'img'         => $node->filter('img')->attr('src'),
                'titulo'      => $node->filter('.titulo')->text(),
                'descricao'   => $node->filter('.descricao')->text(),
                'disponivel'  => strpos($node->attr('class'), 'li-indisponivel') === false,
                'codigo-item' => explode('-', $node->filter('input.form-control.qty')->attr('id'))[2]
            ];
            $descricao = explode(' - ', $item['descricao']);
            $keyLast = array_key_last($descricao);
            if ($keyLast) {
                $item['marca'] = $descricao[$keyLast];
                $item['descricao'] = str_replace(' - '.$item['marca'], '', $item['descricao']);
            } else {
                $item['marca'] = null;
            }
            return $item;
        });
    }
    
    private function writeToCsv($estado, $cidade, $instituicao, $periodo, $lista, $itens)
    {
        foreach($itens as $item) {
            $item['estado'] = $estado;
            $item['cidade'] = $cidade;
            $item['instituicao'] = $instituicao;
            $item['periodo'] = $periodo;
            $item['lista-nome'] = $lista['nome'];
            $item['lista-total'] = $lista['total'];
            $item['lista-codigo'] = $lista['codigo'];
            fputcsv($this->fileHandle, $item);
        }
    }
}