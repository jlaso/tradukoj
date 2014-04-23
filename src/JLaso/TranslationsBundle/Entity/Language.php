<?php
namespace JLaso\TranslationsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Entity(repositoryClass="JLaso\TranslationsBundle\Entity\Repository\LanguageRepository")
 * @ORM\Table(name="translations_language")
 * @ORM\HasLifecycleCallbacks
 */
class Language
{
    /**
     * @var integer $id
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string $locale in format 639-1
     *
     * http://en.wikipedia.org/wiki/ISO_3166-1_alpha-2
     *
     * @ORM\Column(name="locale", type="string", length=2, unique=true)
     */
    protected $locale;

    /**
     * @var string $name
     *
     * @ORM\Column(name="name", type="string", length=100, unique=true)
     */
    protected $name;

    /**
     * @var string $dir
     *
     * <element dir="ltr|rtl|auto">
     *
     * @ORM\Column(name="dir", type="string", length=4, nullable=true)
     */
    protected $dir;

    /**
     * @var \DateTime $createdAt
     *
     * @ORM\Column(name="created_at", type="datetime")
     * @Assert\NotNull()
     * @Assert\DateTime()
     */
    protected $createdAt;

    public function __construct()
    {
        $this->createdAt      = new \DateTime();
    }

    public function __toString()
    {
        return $this->locale . ' ' . $this->name;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set createdAt
     *
     * @param  \DateTime $createdAt
     */
    public function setCreatedAt($createdAt = null)
    {
        $this->createdAt = ($createdAt == null) ? new \DateTime() : $createdAt;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param string $dir
     */
    public function setDir($dir)
    {
        $this->dir = $dir;
    }

    /**
     * @return string
     */
    public function getDir()
    {
        return $this->dir;
    }

    /**
     * @param string $locale
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function asArray()
    {
        return array(
            'name'       => $this->name,
            'dir'        => $this->dir,
            'created_at' => $this->createdAt,
            'id'         => $this->id,
            'locale'     => $this->locale,
        );
    }


}
