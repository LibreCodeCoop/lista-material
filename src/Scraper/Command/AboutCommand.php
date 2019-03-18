<?php

namespace ListaMaterial\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class AboutCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('about')
            ->setDescription('Exibe informações breves sobre o Consulta Material.')
            ->setHelp(<<<HELP
                <info>php consulta-material.phar about</info>

                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->write(<<<HELP
            <info>Consulta Material</info>
            <comment>Coleta de dados de material escolar.</comment>
            Veja https://github.com/lyseontech/consulta-material/ para mais informações.

            HELP
        );
    }
}
