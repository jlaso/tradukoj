<?php

namespace JLaso\TranslationsBundle\Controller;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManager;
use JLaso\TranslationsBundle\Document\File;
use JLaso\TranslationsBundle\Document\Repository\TranslatableDocumentRepository;
use JLaso\TranslationsBundle\Document\TranslatableDocument;
use JLaso\TranslationsBundle\Entity\Permission;
use JLaso\TranslationsBundle\Entity\Project;
use JLaso\TranslationsBundle\Entity\Repository\LanguageRepository;
use JLaso\TranslationsBundle\Entity\Repository\ProjectRepository;
use JLaso\TranslationsBundle\Entity\TranslationLog;
use JLaso\TranslationsBundle\Entity\User;
use JLaso\TranslationsBundle\Exception\AclException;
use JLaso\TranslationsBundle\Form\Type\NewProjectType;
use JLaso\TranslationsBundle\Service\MailerService;
use JLaso\TranslationsBundle\Service\Manager\TranslationsManager;
use JLaso\TranslationsBundle\Service\RestService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ODM\MongoDB\DocumentManager;
use JLaso\TranslationsBundle\Document\Repository\TranslationRepository;
use JLaso\TranslationsBundle\Document\Translation;

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
