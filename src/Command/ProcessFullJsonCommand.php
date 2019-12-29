<?php

namespace App\Command;

use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Exception as SpreadsheetWriterException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessFullJsonCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected static $defaultName = 'process-json';

    /**
     * {@inheritDoc}
     */
    protected function configure(): void
    {
        $this->setDescription('Make SSU job for themselves')
            ->setHelp('Simplify publications list processing')
            ->addArgument('inputFile', InputArgument::REQUIRED, 'Input file path')
            ->addArgument('outputFile', InputArgument::REQUIRED, 'Output file path')
            ->addOption('faculty', 'f', InputOption::VALUE_OPTIONAL, <<<EOL
Faculty ID:
1 -- МЕДИЧНИЙ ІНСТИТУТ (МІ)
2 -- ФАКУЛЬТЕТ ЕЛЕКТРОНІКИ ТА ІНФОРМАЦІЙНИХ ТЕХНОЛОГІЙ (ЕЛІТ)
3 -- ФАКУЛЬТЕТ ІНОЗЕМНОЇ ФІЛОЛОГІЇ ТА СОЦІАЛЬНИХ КОМУНІКАЦІЙ (ІФ СК)
4 -- ФАКУЛЬТЕТ ТЕХНІЧНИХ СИСТЕМ І ЕНЕРГОЕФЕКТИВНИХ ТЕХНОЛОГІЙ (ТеСЕТ)
5 -- НАВЧАЛЬНО-НАУКОВИЙ ІНСТИТУТ ФІНАНСІВ, ЕКОНОМІКИ ТА МЕНЕДЖМЕНТУ ІМЕНІ ОЛЕГА БАЛАЦЬКОГО (ННІ ФЕМ)
6 -- НАВЧАЛЬНО-НАУКОВИЙ ІНСТИТУТ ПРАВА (ННІП)
7 -- НАВЧАЛЬНО-НАУКОВИЙ ІНСТИТУТ БІЗНЕС ТЕХНОЛОГІЙ «УАБС» (ННІ БТ «УАБС»)
8 -- Шосткинський інститут СумДУ
9 -- Конотопський інститут СумДУ
10 -- Кафедра військової підготовки
All faculties will be processed by default
EOL
);
    }

    /**
     * {@inheritDoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($faculty = $input->getOption('faculty')) {
            $faculty = [$faculty];

            if (\in_array(9, $faculty)) {
                $faculty = [200, 209];
            } elseif (\in_array(10, $faculty)) {
                $faculty = [201];
            }
        }

        $parsedData = $this->processInput(
            json_decode(file_get_contents($input->getArgument('inputFile')), true),
            $faculty
        );
        $this->buildOutput($parsedData, $input->getArgument('outputFile'));

        return 0;
    }

    /**
     * Process input JSON and build data array
     *
     * @param array $json
     * @param array $faculties
     *
     * @return array
     */
    private function processInput(array $json, array $faculties): array
    {
        $parsedData = [];

        foreach ($json as $datum) {
            $item = [
                'title' => $datum['job_title'],
                'type' => $datum['types'],
                'scopus' => $datum['indexing'] !== 'ні',
                'wos' => $datum['wos'] !== 'ні',
                'country' => $datum['country'],
                'year' => $datum['pubyear'],
                'authors' => [],
                'faculty' => [],
                'department' => [],
            ];

            $isOnCurrentFaculty = \count($faculties) === 0;

            foreach ($datum['author'] as $author) {
                if (in_array($author['pib'], $item['authors']) === false) {
                    $item['authors'][] = $author['pib'];
                }

                if (in_array($author['fac']['faculty'], $item['faculty']) === false) {
                    $item['faculty'][] = $author['fac']['faculty'];
                }

                if (in_array($author['dep']['department'], $item['department']) === false) {
                    $item['department'][] = $author['dep']['department'];
                }

                if (!$isOnCurrentFaculty && \in_array($author['fac']['id'], $faculties)) {
                    $isOnCurrentFaculty = true;
                }
            }

            if ($isOnCurrentFaculty) {
                $item['authors'] = implode("\n", $item['authors']);
                $item['faculty'] = implode("\n", $item['faculty']);
                $item['department'] = implode("\n", $item['department']);

                $parsedData[] = $item;
            }
        }

        return $parsedData;
    }

    /**
     * Build output
     *
     * @param array  $data
     * @param string $outputPath
     *
     * @throws SpreadsheetException
     * @throws SpreadsheetWriterException
     */
    private function buildOutput(array $data, string $outputPath): void
    {
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->setActiveSheetIndex(0)
            ->fromArray($data, null, 'A2');
        $worksheet->setCellValue('A1', 'Назва');
        $worksheet->setCellValue('B1', 'Тип');
        $worksheet->setCellValue('C1', 'Scopus');
        $worksheet->setCellValue('D1', 'WoS');
        $worksheet->setCellValue('E1', 'Країна');
        $worksheet->setCellValue('F1', 'Рік публікації');
        $worksheet->setCellValue('G1', 'Автор');
        $worksheet->setCellValue('H1', 'Факультет');
        $worksheet->setCellValue('I1', 'Кафедра');

        foreach ($worksheet->getColumnIterator('B', 'I') as $column) {
            $worksheet->getColumnDimension($column->getColumnIndex())->setAutoSize(true);
        }

        $worksheet->getColumnDimension('A')->setWidth(100);
        $worksheet->setAutoFilter('B1:I' . \count($data));

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($outputPath);
    }
}