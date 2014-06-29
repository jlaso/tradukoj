<?php

/**
 * @author Joseluis Laso <jlaso@joseluislaso.es>
 */

namespace JLaso\TranslationsBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Document\Repository\TranslationRepository;
use JLaso\TranslationsBundle\Document\Translation;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\ProjectRepository;
use JLaso\TranslationsBundle\Entity\User;
use JLaso\TranslationsBundle\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;

class ImportExcelCommand extends ContainerAwareCommand
{

    /** @var  string */
    protected $name;
    /** @var  string */
    protected $description;

    protected function configure()
    {
        $this->name        = 'jlaso:translations:import:excel';
        $this->description = 'Import translations from an Excel document';
        $this
            ->setName($this->name)
            ->setDescription($this->description)
            ->addArgument('project', InputArgument::REQUIRED, 'project')
            ->addArgument('excel', InputArgument::REQUIRED, 'excel doc')
            ->addArgument('language', InputArgument::REQUIRED, 'language')
        ;
    }

    protected function substitute($keys, $needle, $reference)
    {

        foreach($keys as $srch=>$replc){

            //$srch = str_replace(array("(",")","[","]"), array('\(','\)','\[','\]'));
            if(preg_match("/\((?<idx>\d+)\)/", $srch, $match)){
                $idx = $match['idx'];
                $regr = "/\({$idx}\)(?<val>.*?)\({$idx}\)/";
                if(preg_match($regr, $reference, $match)){
                    $replc = "%".$match['val']."%";
                }else{
                    $regr = "/\({$idx}\)(.*?)\({$idx}\)/";
                    $replc = "%$1%";
                };
            }else{
                if(preg_match("/\[(?<idx>\d+)\]/", $srch, $match)){
                    $idx = $match['idx'];
                    $regr = "/\[\s?{$idx}\s?\]/";  //print "\n\t$idx\t$regr\t$replc\n";
                }else{
                    die("error in substitute $srch=>$replc");
                }
            }
            $needle = preg_replace($regr, $replc, $needle);
        }

        return $needle;
    }

    protected function getCellValue(\PHPExcel_Worksheet $sheet, $coord)
    {
        $cell = $sheet->getCell($coord);
        if($cell){
            return $cell->getValue();
        }
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $project   = $input->getArgument('project');
        $file      = $input->getArgument('excel');
        $language  = $input->getArgument('language');

        $phpExcel  = $container->get('phpexcel');

        /** @var \PHPExcel $excel */
        $excel     = $phpExcel->createPHPExcelObject($file);

        //ld($excel->getActiveSheet());
        //ld($excel->getActiveSheet()->getCell('A1')->getValue());

        $keySheet = $excel->getSheetByName('key');
        $key = array(); //array_flip(json_decode($keySheet->getCell('A1'), true));
        foreach($keySheet->getRowIterator() as $row){

            $rowNum = $row->getRowIndex();
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false); // Loop all cells, even if it is not set

            foreach ($cellIterator as $cell) {
                /** @var \PHPExcel_Cell $cell */
                $cellValue = $cell->getCalculatedValue();
                switch($cell->getColumn()){
                    case("A"):
                        $index = "[$rowNum]";
                        break;
                    case("B"):
                        $index = "($rowNum)";
                        break;
                };
                if (!is_null($cellValue)) {
                    $key[$index] = $cellValue;
                }
            }
        }

        //print_r($key); die;

        $worksheet = $excel->getSheetByName($language);

        //foreach ($excel->getWorksheetIterator() as $worksheet) {
            $output->writeln('<comment>Worksheet - ' . $worksheet->getTitle() . "</comment>");

            foreach ($worksheet->getRowIterator() as $row) {
                /** @var \PHPExcel_Worksheet_Row $row */
                $index = $row->getRowIndex();

                if(true || in_array($index, array(11,226))){
                    $output->write("<comment>$index</comment>");

                    $rowNum = $row->getRowIndex();
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false); // Loop all cells, even if it is not set

                    $keyName   = $this->getCellValue($worksheet, "A{$rowNum}");
                    $reference = $this->getCellValue($worksheet, "B{$rowNum}");
                    $message   = $this->getCellValue($worksheet, "C{$rowNum}");

                    $substituted = $this->substitute($key, $message, $reference);

                    $output->write(sprintf("\t<info>%s</info> => %s => <comment>%s</comment>", $keyName, $reference, $substituted));
//
//                    foreach ($cellIterator as $cell) {
//                        $cellValue = $cell->getCalculatedValue();
//                        if (!is_null($cell)) {
//                            $calculatedValue = $this->substitute($key, $cellValue);
//                            $output->write("\t<info>$calculatedValue</info> || ");
//                        }
//                    }
                    echo "\n";
                }
            }
        //}


        die('ok');

        /** @var DocumentManager $dm */
        $dm = $container->get('doctrine.odm.mongodb.document_manager');
        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');

        /** @var ProjectRepository $projectRepository */
        $projectRepository = $em->getRepository('TranslationsBundle:Project');
        /** @var TranslationRepository $translationsRepository */
        $translationsRepository = $dm->getRepository('TranslationsBundle:Translation');

        if(intval($project)){
            $project = $projectRepository->find($project);
        }else{
            $project = $projectRepository->findOneBy(array('name' => trim(strtolower($project))));
        }
        /** @var Project $project */
        if(!$project){
            throw new \Exception('Project not found');
        }
        /** @var Translation[] $translations */
        $translations = $translationsRepository->findBy(
            array(
                'projectId' => $project->getId(),
                'catalog'   => $catalog,
                'deleted'   => false,
            ),
            array('key'=>'ASC')
        );

        foreach($translations as $translation){

            if(preg_match("/$search/i", $translation->getKey(), $match)){
                    $output->writeln(sprintf("\tFound (%s) in [%s]<info>%s</info> in catalog <comment>%s</comment>", $match[0], $translation->getBundle(), $translation->getKey(), $translation->getCatalog()));
            }

        }

        $output->writeln(" done!");
    }



}