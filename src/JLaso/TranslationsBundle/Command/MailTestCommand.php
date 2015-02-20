<?php

/**
 * @author Joseluis Laso <jlaso@joseluislaso.es>
 */
namespace JLaso\TranslationsBundle\Command;

use JLaso\TranslationsBundle\Entity\User;
use JLaso\TranslationsBundle\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Container;

class MailTestCommand extends ContainerAwareCommand
{

    /** @var  string */
    protected $name;
    /** @var  string */
    protected $description;

    protected function configure()
    {
        $this->name        = 'jlaso:mail:test';
        $this->description = 'test for mail confg';
        $this
            ->setName($this->name)
            ->setDescription($this->description)
            ->addArgument('email', InputArgument::REQUIRED, 'email')
        ;
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $container = $this->getContainer();

        /** @var MailerService $mailer */
        $mailer = $container->get('jlaso.mailer_service');
        $envir  = $container->get('kernel')->getEnvironment();
        $email  = $input->getArgument('email');

        $user = new User();
        $user->setEmail($email);

        $send = $mailer->sendWelcomeMessage($user);

        $output->writeln($send);
        $output->writeln(" done!");
    }
}
