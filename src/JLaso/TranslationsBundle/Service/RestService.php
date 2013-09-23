<?php
/**
 * @author jlaso@joseluislaso.es
 */

namespace JLaso\TranslationsBundle\Service;

use Symfony\Component\HttpFoundation\Request;

class RestService
{
//
//    /** @var  Request */
//    protected $request;

    function __construct(/*Request $request*/)
    {
//        $this->request = $request;
    }

    /**
     * @param string $message
     *
     * @return mixed
     */
    public function exception($message)
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
    public function resultOk($data = array())
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