<?php

namespace JLaso\TranslationsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * Class HomeController
 * @package JLaso\TranslationsBundle\Controller
 * @Route("/")
 */
class HomeController extends Controller
{

    /**
     * @Route("/", name="home")
     * @Template()
     */
    public function indexAction()
    {
        return array();
    }

    /**
     * @Route("/timeline", name="timeline")
     */
    public function timelineAction()
    {
        print <<<EOD

    <iframe src='http://cdn.knightlab.com/libs/timeline/latest/embed/index.html?source=0AgDHQ1IezjeAdEdTanNRRTE0TDdOMW1HWTVYVEtzMWc&font=NixieOne-Ledger&maptype=TERRAIN&lang=es&start_at_end=true&height=650'
            width='100%' height='650' frameborder='0'>

    </iframe>

EOD;
        die;
    }
}
