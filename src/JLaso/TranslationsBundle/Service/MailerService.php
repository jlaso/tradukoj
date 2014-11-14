<?php

namespace JLaso\TranslationsBundle\Service;

use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\Routing\RouterInterface;
use Doctrine\ORM\EntityManager;


class MailerService
{
    const CONTACT_MAIL = 'jlaso@joseluislaso'; //'hello@tradukoj.com';
    const SUPPORT_MAIL = 'jlaso@joseluislaso'; //'support@tradukoj.com';

    const CONTENT_TYPE = 'text/html';
    const SELF_NAME    = 'Tradukoj, translations for developers';

    protected $mailer;
    protected $templating;
    protected $em;
    protected $router;
    protected $from_default = 'no-reply@tradukoj.com';
    protected $locale;

    public function __construct(\Swift_Mailer $mailer, EngineInterface $templating, EntityManager $entityManager, RouterInterface $router, $locale)
    {
        $this->mailer     = $mailer;
        $this->templating = $templating;
        $this->em         = $entityManager;
        $this->router     = $router;
        $this->locale     = $locale;
    }

    /**
     *
     * @param string $subject
     * @param string $to
     * @param array  $from
     * @param string $template
     * @param array  $parameters
     * @param string $replyTo
     *
     * @return int|boolean
     */
    protected function sendMail($subject, $to, $template, $parameters = array(), $from = null, $replyTo = null)
    {
        $from    = ($from === null) ? array($this->from_default => self::SELF_NAME) : $from;
        $content = $this->templating->render($template, $parameters);
        /** @var \Swift_Message $message */
        $message = \Swift_Message::newInstance()
            ->setSubject($subject)
            ->setFrom($from)
            ->setTo($to)
            ->setBody($content, self::CONTENT_TYPE)
        ;
        if (null !== $replyTo) {
            $message->setReplyTo($replyTo);
        }

        try{
            $result = $this->mailer->send($message);
        }catch(\Exception $e){
            $result = $e->getMessage();
            //print $result;
        }

        return $result;
    }

    /**
     * @param $template
     *
     * @return string
     */
    protected function templateName($template)
    {
        return sprintf('TranslationsBundle:Mails:%s/' . $template, $this->locale);
    }

    public function sendWelcomeMessage(User $user)
    {
        $subject    = 'Welcome user to ' . self::SELF_NAME;
        $template   = $this->templateName('welcomeUser.html.twig');
        $parameters = array(
            'user'      => $user,
            'subject'   => $subject,
            'urls'      => array(
                'homePage' => $this->router->generate('home', array(), true)
            )
        );
        $emailTo = $user->getEmail();

        return $this->sendMail($subject, $emailTo, $template, $parameters);
    }

    public function sendNewProjectMessage(Project $project, User $user)
    {
        $subject    = 'New project created in ' . self::SELF_NAME;
        $template   = $this->templateName('newProject.html.twig');
        $parameters = array(
            'user'      => $user,
            'project'   => $project,
            'subject'   => $subject,
            'urls'      => array(
                'homePage' => $this->router->generate('home', array(), true)
            ),
            'translations_name' => self::SELF_NAME
        );
        $emailTo = $user->getEmail();

        return $this->sendMail($subject, $emailTo, $template, $parameters);
    }

    public function sendContactForm($contact)
    {
        $subject    = 'Contact Form ' . self::SELF_NAME;
        $template   = 'TranslationsBundle:Mails:contactForm.html.twig';
        $parameters = array('contact' => $contact);

        return $this->sendMail($subject, $template, $parameters, self::CONTACT_MAIL);
    }


    public function sendSupportForm($support)
    {
        $subject    = 'Help Form ' . self::SELF_NAME;
        $template   = 'TranslationsBundle:Mails:supportForm.html.twig';
        $parameters = array('support' => $support);

        return $this->sendMail($subject, $template, $parameters, self::SUPPORT_MAIL);
    }


    public function sendResettingPasswordLink(User $user)
    {
        $subject    = 'Forgotten password (' . self::SELF_NAME . ')';
        $template   = 'TranslationsBundle:Mails:resettingPasswordLink.html.twig';
        $parameters = array(
            'subject' => $subject,
            'user'    => $user,
            'urls'    => array(
                'resettingPassword' => $this->router->generate('resetting_password_new', array(
                        'confirmationToken' => $user->getConfirmationToken()), true
                ),
            )
        );

        return $this->sendMail($subject, $template, $parameters, $user->getEmail());
    }


    public function sendResettingPasswordConfirmed(User $user)
    {
        $subject    = 'Your password has been reset! (' . self::SELF_NAME . ')';
        $template   = 'TranslationsBundle:Mails:resettingPasswordConfirmed.html.twig';
        $parameters = array(
            'subject' => $subject,
            'user'    => $user,
            'urls'    => array(
                'userIndex' => $this->router->generate('user_index', array(), true),
                'contactUs' => $this->router->generate('contact_us', array(), true),
            )
        );

        return $this->sendMail($subject, $template, $parameters, $user->getEmail());
    }


}

