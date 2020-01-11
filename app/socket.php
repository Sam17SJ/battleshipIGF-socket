<?php
namespace MyApp;


use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;



class Socket implements MessageComponentInterface {

    public function __construct()
    {
        $this->clients = new \SplObjectStorage;
        $this->jugadores = new \SplObjectStorage;
        $this->jugadoresListos =  array();
        $this->juegos =array ();
    }

    public function onOpen(ConnectionInterface $conn) {

        // Store the new connection in $this->clients
        $this->clients->attach($conn);
        $this->jugadores->attach($conn);

        echo "Un nuevo jugador! ({$conn->resourceId})\n";

    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg,true);
        switch ($data['command']) {
            case "Listo":
                $jugador=array('con' =>$from->resourceId,'username'=>$data['user']);
                //"{'con':".$from->{'resourceId'}." ,'username':".$data['user']."}";                
                $this->jugadoresListos[]=$jugador;
               // $this->JugadoresListos.push($jugador);
                $context = stream_context_create([
                    "http" => [
                        "header" => [
                            "Content-Type: application/json ",
                            "Authorization: Bearer ".$data['token']  
                        ]
                    ]
                ]);
                $datos = json_decode(file_get_contents("http://127.0.0.1:8000/api/barco/barcos", false, $context),true);
                echo "Buscando contrincante\n";
                $this->onEmparejamiento($from,$data);

            break;
            case "Mensajes":
                foreach ( $this->clients as $client ) {

                    if ( $from->resourceId == $client->resourceId ) {
                        continue;
                    }
                   $msj=$data['msj'];
                    
                    $client->send( "Client $from->resourceId said $msj" );
                }
            break;
            case "Disparo":
                $this->disparar($from,$data);
            break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        $this->clients->detach($conn);
        $this->jugadores->detach($conn);
        $i=-1;
        foreach($this->jugadoresListos as $j){
            $i++;
            if ($conn->resourceId ==$j['con']){
                unset($this->jugadoresListos[$i]);
                echo "Se elimino de la lista de espera\n";
            }
        }
        echo "Adios al jugador! ({$conn->resourceId})\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
    }


    private function onEmparejamiento(ConnectionInterface $conn,$data){
        $n=count($this->jugadoresListos);
        $jugador1;
        $jugadorCon;
        $jugador2;
        $bandera=true;
        if ($n>1){
            $i=-1;
            $x=-1;
            foreach ($this->jugadoresListos as $j){
                $i++;
                if ($conn->resourceId ==$j['con']){
                    $bandera=false;
                    $x=$i;
                    continue;
                }
                $jugador1=$j;
                if( $bandera){
                    foreach($this->jugadoresListos as $j2){
                        $x++;
                        if ($conn->resourceId ==$j2['con']){
                            $jugador2=$j2;
                            break;
                        }
                    }
                }else{
                    $jugador2=$this->jugadoresListos[$x]['username'];
                }
            break;
            };
            unset($this->jugadoresListos[$x]);
            unset($this->jugadoresListos[$i]);
            foreach($this->jugadores as $j){
                if($j->resourceId==$jugador1['con']){
                    echo "Enviando noticia a jugador 1\n";
                    $jugadorCon=$j;
                }
            }
            echo "Enviando noticia a jugador 2\n";
            echo"Se emparejo a {$jugador1['username']} con {$jugador2['username']}\n";
            ///////////////////////////Envio de datos/////////////////
            //API Url
            $url = 'http://127.0.0.1:8000/api/juego/iniciar';
            
            //Initiate cURL.
            $ch = curl_init($url);
            
            //The JSON data.
            $jsonData = array(
                'user' => $jugador1['username'],
                'user2' => $jugador2['username']
            );
            //Encode the array into JSON.
            $jsonDataEncoded = json_encode($jsonData);
            
            //Tell cURL that we want to send a POST request.
            curl_setopt($ch, CURLOPT_POST, 1);
            
            //Attach our encoded JSON string to the POST fields.
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
            
            $header = array('Content-Type: application/json',"Authorization: Bearer ".$data['token']);
            //Set the content type to application/json
            curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
            
            //Devolver lo que transfiere
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
            
            //Execute the request
            $result = curl_exec($ch);

            /////////////////////////////Envio de datos///////////////////////////
            $datos = json_decode($result,true);

            $ms1=array('command'=>'PlayGame','grid'=>$datos['tablero1'],'gridE'=>$datos['tablero2'],'idJuego'=>$datos['idJuego'],'player'=>1);
            $ms2=array('command'=>'PlayGame','grid'=>$datos['tablero2'],'gridE'=>$datos['tablero1'],'idJuego'=>$datos['idJuego'],'player'=>2);
            $jugadorCon->send(json_encode($ms1));
            $conn->send(json_encode($ms2));

            $juego =array('id'=>$datos['idJuego'],'j1' =>$jugadorCon->resourceId, 'j2' => $conn->resourceId );
            $this->juegos[]=$juego;
        }
    }

        private function disparar(ConnectionInterface $conn,$data){
            ///////////////////////////Envio de datos/////////////////
            //API Url
            $url = 'http://127.0.0.1:8000/api/juego/disparar';
            
            //Initiate cURL.
            $ch = curl_init($url);
            
            //The JSON data.
            $jsonData = array(
                'juego' => $data['idJuego'],
                'X' => $data['x'],
                'Y' => $data['y'],
                'grid' => $data['grid']
            );
            //Encode the array into JSON.
            $jsonDataEncoded = json_encode($jsonData);
            
            //Tell cURL that we want to send a POST request.
            curl_setopt($ch, CURLOPT_POST, 1);
            
            //Attach our encoded JSON string to the POST fields.
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
            
            $header = array('Content-Type: application/json',"Authorization: Bearer ".$data['token']);
            //Set the content type to application/json
            curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
            
            //Devolver lo que transfiere
            curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
            
            //Execute the request
            $result = curl_exec($ch);

            /////////////////////////////Envio de datos///////////////////////////
            $datos = json_decode($result,true);

            error_log($datos);

            $to;
            $game;

            foreach($this->juegos as $j){
                if ($data['idJuego'] ==$j['id']){
                    $game=$j;
                }
            }
            foreach($this->jugadores as $j){
                if($j->resourceId==$game['j1'] || $j->resourceId==$game['j2']){
                    if( $conn->resourceId!=$j->resourceId){
                        $to=$j;
                    }
                }
            }
            if($datos!=100){
                $ms1=array('command'=>'Esperar','x'=>$data['x'],'y'=>$data['y'],'resp'=>$datos);
                $ms2=array('command'=>'Dispare','x'=>$data['x'],'y'=>$data['y'],'resp'=>$datos);
                $conn->send(json_encode($ms1));
                $to->send(json_encode($ms2));
            }else{
                $ms1=array('command'=>'Gano','x'=>$data['x'],'y'=>$data['y'],'resp'=>$datos);
                $ms2=array('command'=>'Perdio','x'=>$data['x'],'y'=>$data['y'],'resp'=>$datos);
                $conn->send(json_encode($ms1));
                $to->send(json_encode($ms2));
                }
            
        }
    }
 


