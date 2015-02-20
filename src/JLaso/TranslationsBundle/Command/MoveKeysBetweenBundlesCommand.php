<?php

/**
 * @author Joseluis Laso <jlaso@joseluislaso.es>
 */
namespace JLaso\TranslationsBundle\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Document\Repository\TranslationRepository;
use JLaso\TranslationsBundle\Document\Translation;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\ProjectRepository;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\DialogHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;

class MoveKeysBetweenBundlesCommand extends ContainerAwareCommand
{

    /** @var  string */
    protected $name;
    /** @var  string */
    protected $description;

    protected function configure()
    {
        $this->name        = 'jlaso:translations:move-keys-between-bundles';
        $this->description = 'moves keys between bundles that assert a criteria';
        $this
            ->setName($this->name)
            ->setDescription($this->description)
            ->addOption('project', null, InputOption::VALUE_REQUIRED, 'project id or slug')
            ->addOption('criteria', null, InputOption::VALUE_REQUIRED, 'criteria (reg exp to search in keys)')
            ->addOption('origin', null, InputOption::VALUE_REQUIRED, 'origin (origin bundle)')
            ->addOption('dest', null, InputOption::VALUE_REQUIRED, 'dest (destination bundle)')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();
        $project   = $input->getOption('project');
        $criteria  = $input->getOption('criteria');
        $origin    = $input->getOption('origin');
        $dest      = $input->getOption('dest');
        $dm        = $container->get('doctrine.odm.mongodb.document_manager');

        /** @var DocumentManager $dm */
        $dm = $container->get('doctrine.odm.mongodb.document_manager');
        /** @var EntityManager $em */
        $em = $container->get('doctrine.orm.default_entity_manager');

        /** @var ProjectRepository $projectRepository */
        $projectRepository = $em->getRepository('TranslationsBundle:Project');
        /** @var TranslationRepository $translationsRepository */
        $translationsRepository = $dm->getRepository('TranslationsBundle:Translation');

        if (intval($project)) {
            $project = $projectRepository->find($project);
        } else {
            $project = $projectRepository->findOneBy(array('name' => trim(strtolower($project))));
        }
        /** @var Project $project */
        if (!$project) {
            throw new \Exception('Project not found');
        }
        /** @var Translation[] $translations */
        $translations = $translationsRepository->findBy(
            array(
                'projectId' => $project->getId(),
                'bundle'    => $origin,
            )
        );

        $toMove = array();
        foreach ($translations as $translation) {
            if (preg_match('/'.$criteria.'/i', $translation->getKey(), $match)) {
                $output->writeln(
                    sprintf(
                        "\tFound key (%s) in catalog <comment>%s</comment>, bundle %s",
                        $translation->getKey(),
                        $translation->getCatalog(),
                        $translation->getBundle()
                    )
                );

                $toMove[] = $translation;
            }
        }

        if (!count($toMove)) {
            $output->writeln("\n\t<info>There are no keys with this criteria/bundle!</info>");
            exit;
        }

        /** @var DialogHelper $dialog */
        $dialog = $this->getHelper('dialog');
        if ($dialog->askConfirmation(
            $output,
            "<question>Confirm that you want to move the previous keys to bundle {$dest} ?</question>",
            false
        )) {
            foreach ($toMove as $translation) {
                $translation->setBundle($dest);
                $dm->persist($translation);
            }

            $dm->flush();
            $output->writeln(" done!");
        }
    }
}
