<?php

namespace JLaso\TranslationsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\Session;

class BaseController extends Controller
{
    const NOPARAMS = null;

    /** @var  Translator */
    protected $translator;

    public function setContainer(ContainerInterface $container = null)
    {
        parent::setContainer($container);

        $this->translator = $container->get('translator');

        if(method_exists($this, 'init')){
            $this->init();
        }
    }

    public function throwError($message, $params = null, $toAction = null, $paramsToAction = array())
    {
        if(null == $params){
            $params = array();
        }
        $message = $this->translator->trans($message, $params);
        /** @var Session $session */
        $session = $this->get('session');
        $session->getFlashBag()->getadd('error', $message);

        if($toAction){
            return $this->redirect($this->generateUrl($toAction, $paramsToAction));
        }else{
            $currentUrl = $this->getRequest()->getUri();
            return $this->redirect($currentUrl);
        }

    }

    public function addNoticeFlash($message, $params = array())
    {
        $message = $this->translator->trans($message, $params);
        $this->get('session')->getFlashBag()->add('notice', $message);
    }

    public function addSuccessFlash($message, $params = array())
    {
        $message = $this->translator->trans($message, $params);
        $this->get('session')->getFlashBag()->add('success', $message);
    }

    public function trans($msg)
    {
        return $this->translator->trans($msg);
    }

    /**
     * @param string $message
     *
     * @return mixed
     */
    protected function exception($message)
    {
        $result = array(
            'result' => false,
            'reason' => $message,
        );

        return $this->printResult($result);
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    protected function printResult($data)
    {
        header('Content-type: application/json');
        print json_encode($data);
        exit;
    }

    /**
     * @param array  $data
     *
     * @return mixed
     */
    protected function resultOk($data = array())
    {
        return $this->printResult(
            array_merge(
                array(
                    'result' => true,
                ),
                $data
            )
        );
    }

}
