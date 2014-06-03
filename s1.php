<?php
$socket = stream_socket_server ('tcp://0.0.0.0:2000', $errno, $errstr);
stream_set_blocking($socket, 0);



$base = event_base_new();
$event = event_new();
event_set($event, $socket, EV_READ | EV_PERSIST, function($s,$f,$b){ ev_accept($s,$f,$b); } /*'ev_accept'*/, $base);
event_base_set($event, $base);
event_add($event);
print "starting...";
//event_base_loop($base);
$loop = 0;
while(true){

    event_base_loop($base, EVLOOP_ONCE | EVLOOP_NONBLOCK);

    usleep(1000);
    if($loop++ > 1000){
        print " . ";
        $loop = 0;
    }
}

$GLOBALS['connections'] = array();
$GLOBALS['buffers'] = array();

function ev_accept($socket, $flag, $base) {
    static $id = 0;

    $connection = stream_socket_accept($socket);
    stream_set_blocking($connection, 0);

    $id += 1;

    $buffer = event_buffer_new($connection, 'ev_read', NULL, 'ev_error', $id);
    event_buffer_base_set($buffer, $base);
    event_buffer_timeout_set($buffer, 30, 30);
    event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
    event_buffer_priority_set($buffer, 10);
    event_buffer_enable($buffer, EV_READ | EV_PERSIST);

    // we need to save both buffer and connection outside
    $GLOBALS['connections'][$id] = $connection;
    $GLOBALS['buffers'][$id] = $buffer;
}

function ev_error($buffer, $error, $id) {
    event_buffer_disable($GLOBALS['buffers'][$id], EV_READ | EV_WRITE);
    event_buffer_free($GLOBALS['buffers'][$id]);
    fclose($GLOBALS['connections'][$id]);
    unset($GLOBALS['buffers'][$id], $GLOBALS['connections'][$id]);
}

function ev_read($buffer, $id) {
    while ($read = event_buffer_read($buffer, 256)) {
        var_dump($read);
    }
}
?>