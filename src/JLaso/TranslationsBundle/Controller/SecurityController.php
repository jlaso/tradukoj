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
class SecurityController extends BaseController
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
        //$this->user                = $this->get('security.context')->getToken()->getUser();
        $this->translator          = $this->container->get('translator');
    }

    /**
     * @Route("/login", name="user_login")
     * @Template()
     */
    public function loginAction()
    {
        $request = $this->getRequest();
        $session = $request->getSession();

        /** @var SecurityContext $securityContext */
        $securityContext = $this->get('security.context');
        $token = $securityContext->getToken();
        if($token){
            /** @var User $user */
            $user = $token->getUser();
            if($user instanceof User){
               // die('asd');
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
     * @param $access_token
     *
     * @return mixed
     */
    protected function getDataFromGitHub($access_token)
    {
        $ch = curl_init('https://api.github.com/user?access_token=' . $access_token);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'tradukoj.com');
        $response = curl_exec($ch);
        //print_r($response);

        return json_decode($response, true);
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
            //ld($response);
            sleep(1);
            if(isset($out[1])){
                $access_token = $out[1];
                /**
                 * {
                "login": "jlaso",
                "id": 1332197,
                "avatar_url": "https://gravatar.com/avatar/05294fb8badbde3c6999bae2c0024165?d=https%3A%2F%2Fidenticons.github.com%2Fce92b9ab89941caa47947924ac110d1f.png&r=x",
                "gravatar_id": "05294fb8badbde3c6999bae2c0024165",
                "url": "https://api.github.com/users/jlaso",
                "html_url": "https://github.com/jlaso",
                "followers_url": "https://api.github.com/users/jlaso/followers",
                "following_url": "https://api.github.com/users/jlaso/following{/other_user}",
                "gists_url": "https://api.github.com/users/jlaso/gists{/gist_id}",
                "starred_url": "https://api.github.com/users/jlaso/starred{/owner}{/repo}",
                "subscriptions_url": "https://api.github.com/users/jlaso/subscriptions",
                "organizations_url": "https://api.github.com/users/jlaso/orgs",
                "repos_url": "https://api.github.com/users/jlaso/repos",
                "events_url": "https://api.github.com/users/jlaso/events{/privacy}",
                "received_events_url": "https://api.github.com/users/jlaso/received_events",
                "type": "User",
                "site_admin": false,
                "name": "Joseluis Laso",
                "company": "Joseluislaso",
                "blog": "http://www.joseluislaso.es",
                "location": "Valencia",
                "email": "wld1373@gmail.com",
                "hireable": true,
                "bio": "Web developper apasionate, with PHP, jQuery, jQuerymobile, Symfony, Doctrine, Slim, CSS3, HTML5 ...",
                "public_repos": 57,
                "public_gists": 2,
                "followers": 2,
                "following": 16,
                "created_at": "2012-01-15T20:51:17Z",
                "updated_at": "2014-01-24T13:24:19Z"
                }
                 */
                $data         = $this->getDataFromGitHub($access_token);
                if(!count($data)){
                    $this->addNoticeFlash('error.github_connect_not_possible');
                    $this->redirect($this->generateUrl('user_login'));
                }
                //ld($data); sleep(4);
                try{
                    $login        = $data['login'];
                    $avatar_url   = $data['avatar_url'];
                    $user         = $this->getUserRepository()->findOneBy(array('username' => $login));
                    $email        = isset($data['email']) ? $data['email'] : $login . '-github@tradukoj.com';
                    if(!$user instanceof User){
                        $user     = $this->getUserRepository()->findOneBy(array('email' => $email));
                    }
                    if(!$user instanceof User){
                        $user = new User();
                        $user->setEmail($email);
                        $user->setName(isset($data['name']) ? $data['name'] : 'unknown');
                        $user->setActived(true);
                        $user->setPassword(uniqid());
                        $user->setUsername($login);
                        $user->addRole('ROLE_TRANSLATOR');
                        $user->setAvatarUrl($avatar_url);
                        $this->em->persist($user);
                        $this->em->flush();
                    }
                    $this->loginAs($user);

                    //return new Response('OK');
                    return $this->redirect($this->generateUrl('user_index'));

                }catch(\Exception $e){
                    print $e->getMessage();
                    var_dump($data);
                }
            }else{
                var_dump($response);

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
            $form->submit($request);

            if ($form->isValid()) {

                /** @var Session $session */
                $session = $this->get('session');
                /** @var EntityManager $em */

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
                try{
                    $send = $mailer->sendWelcomeMessage($user);
                }catch(\Exception $e){

                }

                if(is_string($send)){
                    $this->addNoticeFlash($send);
                }

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

    protected function loginAs(User $user, $providerKey = 'user_index')
    {
        $token = new UsernamePasswordToken($user, $user->getPassword(), $providerKey, $user->getRoles());
        $this->container->get('security.context')->setToken($token);
        /** @var Session $session */
        $session = $this->get('session');
        $session->set('_security_'.$providerKey, serialize($token));
    }

}
