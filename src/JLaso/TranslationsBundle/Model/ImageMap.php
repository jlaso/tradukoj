<?php
/**
 * Created by PhpStorm.
 * User: jl
 * Date: 10/01/14
 * Time: 19:49
 */

namespace JLaso\TranslationsBundle\Model;


class ImageMap
{

    protected $x;
    protected $y;
    protected $w;
    protected $h;

    function __construct($x = 0, $y = 0, $w = 0, $h = 0)
    {
        $this->h = $h;
        $this->w = $w;
        $this->x = $x;
        $this->y = $y;
    }

    /**
     * @param int $h
     *
     * @return $this
     */
    public function setH($h)
    {
        $this->h = $h;

        return $this;
    }

    /**
     * @return int
     */
    public function getH()
    {
        return $this->h;
    }

    /**
     * @param int $w
     *
     * @return $this
     */
    public function setW($w)
    {
        $this->w = $w;

        return $this;
    }

    /**
     * @return int
     */
    public function getW()
    {
        return $this->w;
    }

    /**
     * @param int $x
     *
     * @return $this
     */
    public function setX($x)
    {
        $this->x = $x;

        return $this;
    }

    /**
     * @return int
     */
    public function getX()
    {
        return $this->x;
    }

    /**
     * @param int $y
     *
     * @return $this
     */
    public function setY($y)
    {
        $this->y = $y;

        return $this;
    }

    /**
     * @return int
     */
    public function getY()
    {
        return $this->y;
    }


} 