<?php

/**
 * @author Joseluis Laso <jlaso@joseluislaso.es>
 */

namespace JLaso\TranslationsBundle\Service\Socket;

use P2\Bundle\RatchetBundle\WebSocket\ApplicationInterface;

class SocketApplication implements ApplicationInterface
{
    public static function getSubscribedEvents()
    {
        return array(
//            'translations.service.socket.register' => 'onRegisterEvent'

        );
    }


//    public function onRegisterEvent()
//    {
//        die('hola');
//    }

}