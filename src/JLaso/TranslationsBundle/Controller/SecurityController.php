<?php
namespace JLaso\TranslationsBundle\Controller;

use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Entity\Repository\UserRepository;
use JLaso\TranslationsBundle\Entity\User;
use JLaso\TranslationsBundle\Form\Type\UserRegistrationType;
use JLaso\TranslationsBundle\Service\MailerService;
use JLaso\TranslationsBundle\Service\Manager\TranslationsManager;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Encoder\EncoderFactory;
use Symfony\Component\Security\Core\SecurityContext;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Cache;


/**
 * @Cache(maxage="0")
 */
class SecurityController extends Controller
{

    const CLIENT_ID = '0334c2cebd3ec46632a3';

    /** @var  EntityManager */
    protected $em;
    protected $config;
    /** @var  TranslationsManager */
    protected $translationsManager;
    /** @var User */
    protected $user;
    /** @var  Translator */
    protected $translator;

    protected function init()
    {
        $this->em                  = $this->container->get('doctrine.orm.default_entity_manager');
        $this->config              = $this->container->getParameter('jlaso_translations');
        $this->translationsManager = $this->container->get('jlaso.translations_manager');
        $this->user                = $this->get('security.context')->getToken()->getUser();
        $this->translator          = $this->container->get('translator');
    }

    /**
     * @Route("/login/", name="user_login")
     * @Template()
     */
    public function loginAction()
    {
        $request = $this->getRequest();
        $session = $request->getSession();

        /** @var User $user */
        $user = $this->get('security.context')->getToken()->getUser();
        if ($this->get('security.context')->isGranted('IS_FULLY_AUTHENTICATED')){
            switch(true){
                case ($user->hasRole(User::ROLE_DEVELOPER)):
                    return $this->redirect($this->generateUrl('user_index') . '#developer-area');
                    break;
                case ($user->hasRole(User::ROLE_TRANSLATOR)):
                    return $this->redirect($this->generateUrl('user_index') . '#translator-area');
                    break;
                case ($user->hasRole(User::ROLE_ADMIN)):
                    return $this->redirect($this->generateUrl('user_index') . '#admin-area');
                    break;
                default:
                    throw new \Exception('Unknow role for user');
            }
        }

        // get the login error if there is one
        if ($request->attributes->has(SecurityContext::AUTHENTICATION_ERROR)) {
            $error = $request->attributes->get(
                SecurityContext::AUTHENTICATION_ERROR
            );
        } else {
            $error = $session->get(SecurityContext::AUTHENTICATION_ERROR);
            $session->remove(SecurityContext::AUTHENTICATION_ERROR);
        }

        return array(
            'last_username' => $session->get(SecurityContext::LAST_USERNAME),
            'error'         => $error,
            'client_id'     => self::CLIENT_ID,
        );
    }

    /**
     * @Route("/user/github-login/callback", name="login_github_callback")
     * @Template()
     */
    public function loginGithubCallbackAction()
    {
        return array();
    }

    /**
     * @Route("/user/do-github-login", name="do_github_login")
     */
    public function doGithubLoginAction()
    {
        $this->init();
        $request       = $this->getRequest();
        $client_secret = 'd945ef6df389f0fa5d95eb638fd74fccf623d218';

        if($code = $request->get('code')){
            $data = sprintf('client_id=%s&client_secret=%s&code=%s', self::CLIENT_ID, $client_secret, urlencode($code));

            $ch = curl_init('https://github.com/login/oauth/access_token');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);

            preg_match('/access_token=([0-9a-f]+)/', $response, $out);
            curl_close($ch);
            if(isset($out[1])){
                $access_token = $out[1];
                $response     = file_get_contents('https://api.github.com/user?access_token=' . $access_token);
                $data         = json_decode($response, true);
                try{
                    $login        = $data['login'];
                    $avatar_url   = $data['avatar_url'];
                    $user         = $this->getUserRepository()->findOneBy(array('username' => $login));
                    $email        = isset($data['email']) ? $data['email'] : $login . '-github@translations.com.es';
                    if(!$user instanceof User){
                        $user     = $this->getUserRepository()->findOneBy(array('email' => $email));
                    }
                    if(!$user instanceof User){
                        $user = new User();
                        $user->setUsername($login);
                        $user->setEmail($email);
                        $user->setName(isset($data['name']) ? $data['name'] : 'unknown');
                        $user->setActived(true);
                        $user->setPassword(uniqid());
                    }
                    $user->setAvatarUrl($avatar_url);
                    $this->em->persist($user);
                    $this->em->flush();
                    $this->loginAs($user);
                    var_dump($data);
                    return new Response('OK');
                }catch(\Exception $e){
                    print $e->getMessage();
                    var_dump($data);
                }
            }else{
                ld($response);

            }
        }

        return new Response('OK');
    }


    /**
     * Register a new User entity.
     *
     * @Route("/register", name="user_register")
     * @Template()
     */
    public function registerAction(Request $request)
    {
        $user  = new User();
        $form = $this->createForm(new UserRegistrationType(), $user);

        if($request->isMethod('POST')){
            $form->bind($request);

            if ($form->isValid()) {

                /**
                 * @var Session         $session
                 * @var EntityManager   $em
                 */
                $session = $this->get('session');
                $em = $this->getDoctrine()->getManager();

                /** @var EncoderFactory $encoderFactory */
                $encoderFactory = $this->container->get('security.encoder_factory');
                $encoder = $encoderFactory->getEncoder($user);
                $user->setPassword($encoder->encodePassword($user->getPassword(), $user->getSalt()));

                $user->setActived(true);
                $user->addRole(User::ROLE_TRANSLATOR);
                $em->persist($user);
                $em->flush();

                // do login and go to homepage
                $token = new UsernamePasswordToken($user, null, 'user_area', $user->getRoles());
                $this->container->get('security.context')->setToken($token);
                $this->get('session')->set('_security_'.'user_area', serialize($token));

                /** @var MailerService $mailer */
                $mailer = $this->get('jlaso.mailer_service');
                $send = $mailer->sendWelcomeMessage($user);

                $session = $this->get('session')->all();

                if (isset($session['_security.user_area.target_path'])) {
                    $url = $session['_security.user_area.target_path'];
                    return $this->redirect($url);
                }

                return $this->redirect($this->generateUrl('user_index'));
            }

        }

        return array(
            'last_username' => $this->get('request')->getSession()->get(SecurityContext::LAST_USERNAME),
            'error'         => null,
            'form'          => $form->createView(),
            'user'          => $user,
        );
    }

    /**
     * @return UserRepository
     */
    protected function getUserRepository()
    {
        return $this->em->getRepository('TranslationsBundle:User');
    }

    protected function loginAs($user, $providerKey = 'user_index')
    {
        $token = new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
        $this->container->get('security.context')->setToken($token);
        /** @var Session $session */
        $session = $this->get('session');
        $session->set('_security_'.$providerKey, serialize($token));
    }

}
