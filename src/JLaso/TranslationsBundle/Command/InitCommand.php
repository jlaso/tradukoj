<?php

/**
 * @author Joseluis Laso <jlaso@joseluislaso.es>
 */
namespace JLaso\TranslationsBundle\Command;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Entity\Permission;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;

class InitCommand extends ContainerAwareCommand
{

    /** @var  string */
    protected $name;
    /** @var  string */
    protected $description;

    protected function configure()
    {
        $this->name        = 'tradukoj:init:data';
        $this->description = 'Initialization of basic data';
        $this
            ->setName($this->name)
            ->setDescription($this->description)
            ->addOption('email', null, InputOption::VALUE_NONE, '--force')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        /** @var EntityManager $em */
        $em = $container->get('doctrine')->getManager();

        // Create first user
        $user  = new User();
        $user->setEmail('admin@tradukoj.com');
        $user->setName('Admin Tradukoj');
        $user->setActive(true);
        $user->addRole(User::ROLE_ADMIN);
        $user->setPassword('Tradukoj$1234');

        /** @var EncoderFactory $encoderFactory */
        $encoderFactory = $container->get('security.encoder_factory');
        $encoder = $encoderFactory->getEncoder($user);
        $user->setPassword($encoder->encodePassword($user->getPassword(), $user->getSalt()));

        $em->persist($user);
        $em->flush();

        $usersColl = new ArrayCollection();
        $usersColl->add($user);

        // create first project
        $project  = new Project();
        $project->setName('tradukoj');
        $project->setApiKey('tradukoj.com');
        $project->setApiSecret('Tradukoj$1234');
        $project->setManagedLocales('en,es');
        $project->setProject('tradukoj');
        //$project->setUsers($usersColl);

        $permission = new Permission();
        $permission->setUser($user);
        $permission->setProject($project);
        $permission->addPermission(Permission::OWNER);
        // Give permission to write in all languages
        $permission->addPermission(Permission::WRITE_PERM, '*');

        $em->persist($permission);

        $em->persist($project);
        $em->flush();

        $output->writeln(" done!");
    }
}
