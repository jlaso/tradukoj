<?php
namespace JLaso\TranslationsBundle\Controller;

use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Entity\User;
use JLaso\TranslationsBundle\Form\Type\UserRegistrationType;
use JLaso\TranslationsBundle\Service\MailerService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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
        );
    }

//    /**
//     * @Route("/login-facebook", name="login_facebook")
//     * @Template()
//     */
//    public function loginFacebookAction()
//    {
//        $facebook = $this->get('jlaso.facebook_client');
//
//        $session = $this->get('session')->all();
//
//        $facebookUser = $facebook->getUser();
//
//        if ($facebookUser) {
//            try {
//                $facebookUserProfile = $facebook->api('/me');
//
//                $userManager = $this->get('onbile.user_manager');
//
//                $user = $userManager->findUserBy(array(
//                        'email' => $facebookUserProfile['email']
//                    ));
//
//                if (!$user) {
//                    $user = $userManager->createFacebookUser($facebookUserProfile);
//                }
//
//                $this->loginAs($user);
//
//                if (isset($session['_security.user_area.target_path'])) {
//                    $url = $session['_security.user_area.target_path'];
//                } else {
//                    $url = $this->generateUrl('user_index');
//                }
//
//                return $this->redirect($url);
//            } catch (\FacebookApiException $e) {
//                $facebookUser = null;
//            }
//        }
//
//        $parameters = array(
//            'scope' => 'email,publish_actions'
//        );
//
//        return $this->redirect($facebook->getLoginUrl($parameters));
//    }
//
//    /**
//     * @Route("/login-twitter", name="login_twitter")
//     * @Template()
//     */
//    public function loginTwAction()
//    {
//        $request = $this->getRequest();
//        $session = $request->getSession();
//
//        $twitterClient = $this->get('onbile.twitter_client');
//
//        $twitterParams = $this->container->getParameter('twitter');
//        $request_token = $twitterClient->getRequestToken($twitterParams['callback']);
//
//        $token = $request_token['oauth_token'];
//        $session->set('oauth_token', $token);
//        $session->set('oauth_token_secret', $request_token['oauth_token_secret']);
//
//        if ($twitterClient->http_code === 200) {
//            $url = $twitterClient->getAuthorizeURL($token);
//
//            return $this->redirect($url);
//        }
//
//        echo 'Could not connect to Twitter. Refresh the page or try again later.';
//
//        return array();
//    }
//
//    /**
//     * @Route("/twitter-callback", name="login_tw_callback")
//     * @Template()
//     */
//    public function loginTwCallbackAction()
//    {
//        $request = $this->getRequest();
//        $session = $request->getSession();
//
//        /* If the oauth_token is old redirect to the connect page. */
//        if ($request->query->has('oauth_token') && $session->get('oauth_token') !== $request->query->get('oauth_token')) {
//            $session->set('oauth_status', 'oldtoken');
//        }
//
//        $twitterClient = $this->get('onbile.twitter_client');
//        $twitterClient->setOAuthToken($session->get('oauth_token'), $session->get('oauth_token_secret'));
//
//        /* Request access tokens from twitter */
//        $access_token = $twitterClient->getAccessToken($request->query->get('oauth_verifier'));
//
//        /* Save the access tokens. Normally these would be saved in a database for future use. */
//        $session->set('access_token', $access_token);
//
//        /* Remove no longer needed request tokens */
//        $session->remove('oauth_token');
//        $session->remove('oauth_token_secret');
//
//        /* If HTTP response is 200 continue otherwise send to connect page to retry */
//        if (200 == $twitterClient->http_code) {
//            /* The user has been verified and the access tokens can be saved for future use */
//            $session->set('status', 'verified');
//
//            $twitterProfile = $twitterClient->get('account/verify_credentials');
//
//            $userManager = $this->get('onbile.user_manager');
//
//            $user = $userManager->findUserByTwitterId($twitterProfile->id);
//
//            if (!$user) {
//                $user = $userManager->createTwitterUser($twitterProfile);
//            }
//
//            $this->loginAs($user);
//
//        } else {
//            /* Save HTTP status for error dialog on connnect page.*/
//            // return $this->redirect('f_twitter_clear_sessions');
//        }
//
//        $sessionAttr = $session->all();
//        if (isset($sessionAttr['_security.user_area.target_path'])) {
//            $url = $sessionAttr['_security.user_area.target_path'];
//        } else {
//            $url = $this->generateUrl('user_index');
//        }
//
//        return $this->redirect($url);
//    }
//
//    private function loginAs($user, $providerKey = 'user_area')
//    {
//        $token = new UsernamePasswordToken($user, null, $providerKey, $user->getRoles());
//        $this->container->get('security.context')->setToken($token);
//        $this->get('session')->set('_security_'.$providerKey, serialize($token));
//    }


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


}
