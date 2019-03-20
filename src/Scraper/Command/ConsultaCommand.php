<?php

namespace ListaMaterial\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Goutte\Client;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Console\Input\InputOption;

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
    private $fileHandle = [];
    private $cidades = [];
    private $instituicoes = [];
    private $separator;
    protected function configure()
    {
        $this
            ->setName('consulta')
            ->setDescription('Realiza consulta de material escolar.')
            ->setDefinition([
                new InputOption('uf', null, InputOption::VALUE_REQUIRED + InputOption::VALUE_IS_ARRAY, 'Lista de UF separados por vírgula', $this->estados)
            ])
            ->setDefinition([
                new InputOption('separator', 's', InputOption::VALUE_OPTIONAL, 'Separador de colunas para o CSV, padrão é vírgula', ',')
            ])
            ->setHelp(<<<HELP
                O comando <info>consulta</info> realiza consulta de empresa.
                HELP
            )
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->separator = $input->getOption('separator');
        $listaUF = $input->getOption('uf');
        $listaUF = array_map('strtoupper', $listaUF);
        $invalid = array_diff($listaUF, $this->estados);
        if($invalid) {
            $output->writeln('<error>UF inválidas:</error> '.implode(',', $invalid));
            return 1;
        }
        $this->client = new Client();
        if(count($listaUF) > 1) {
            foreach($listaUF as $UF) {
                pclose(popen('php bin/consulta-material --uf='.$UF.' &', 'r'));
            }
            return;
        }
        $this->ProcessUF($listaUF[0]);
    }

    private function ProcessUF($estado)
    {
        $this->createCSV($estado);
        $this->requireCidades($estado);
        foreach($this->cidades as $cidade) {
            $this->requireInstituicao($estado, $cidade);
            foreach($this->instituicoes as $instituicao) {
                $this->requirePeriodo($estado, $cidade, $instituicao);
                foreach ($this->periodos as $periodo) {
                    $this->requireListaMaterial($estado, $cidade, $instituicao, $periodo);
                    foreach($this->listas as $lista) {
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
        $handle = fopen('log', 'a');
        fwrite($handle, $estado . ',' . date('Y-m-d H:i:s') . "\n");
        fclose($handle);
    }

    private function requireCidades($estado)
    {
        $crawler = $this->client->request('GET',
            'https://www.suryadental.com.br/academico/index/getCidade/?estado='.$estado
        );
        $this->cidades = $crawler->filter('option')->each(function ($node) {
            return $node->attr('value');
        });
        $this->cidades = array_filter($this->cidades, 'strlen');
    }

    private function requireInstituicao($estado, $cidade)
    {
        $crawler = $this->client->request('GET',
            'https://www.suryadental.com.br/academico/index/getInstituicao/?'.
            http_build_query([
                'cidade' => $cidade,
                'estado' => $estado
            ])
        );
        $this->instituicoes = $crawler->filter('option')->each(function ($node) {
            return $node->attr('value');
        });
        $this->instituicoes = array_filter($this->instituicoes, 'strlen');
    }

    private function requirePeriodo($estado, $cidade, $instituicao)
    {
        $crawler = $this->client->request('GET',
            'https://www.suryadental.com.br/academico/index/getPeriodo/?'.
            http_build_query([
                'instituicao' => $instituicao,
                'cidade' => $cidade,
                'estado' => $estado
            ])
        );
        $this->periodos = $crawler->filter('option')->each(function ($node) {
            return $node->attr('value');
        });
        $this->periodos = array_filter($this->periodos, 'strlen');
    }

    private function requireListaMaterial($estado, $cidade, $instituicao, $periodo)
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
        $this->listas = $this->crawlerListaMaterial->filter('li.col-xs-12')->each(function (Crawler $node) {
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
    
    private function createCSV($estado)
    {
        $this->fileHandle[$estado] = fopen('lista-academica-surya-'.$estado.'-'.date('ymdHis').'.csv', 'w');
        fputcsv(
            $this->fileHandle[$estado],
            [
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
            ],
            $this->separator
        );
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
            fputcsv($this->fileHandle[$estado], $item, $this->separator);
        }
    }

    public function __destruct()
    {
        array_map('fclose', $this->fileHandle);
    }
}
