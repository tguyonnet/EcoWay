<?php
/**
 * Created by PhpStorm.
 * User: usersio
 * Date: 04/10/18
 * Time: 17:11
 */



namespace Controllers;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use RedBeanPHP\R;
use DateTime;
use DateInterval;

class Vehicule
{


    /**
     * @param $coordDepart
     * @param $coordArrivee
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getTimeAndDistanceFromCoord($coordDepart,$coordArrivee){
        $client = new \GuzzleHttp\Client();

        $res = $client->request('GET', 'https://graphhopper.com/api/1/route?point='.$coordDepart.'&point='.$coordArrivee.'&vehicle=car&locale=de&key=252e0609-b2cf-4408-845e-f6ca29e1a2a9');        // Obtenir la distance entre les deux points.
        $info = json_decode($res->getBody());

        $distance = $info->paths[0]->distance;
        $time = $info->paths[0]->time;

        $time = floatval($time)/(3600000);          //Mettre le temps (milliseconde) en heure.
        $distance = floatval($distance)/1000;       //Mettre la istance (en m) en Kms.
        $vitesseMoy = $distance/$time;              //calcul de la vitesse moyenne.


        $array = ['distance'=>$distance,'time'=>$time,'vitesseMoy'=>$vitesseMoy];
        return $array;
    }


    /**
     * @param $arrayTimeDistance -> Array composé de time, distance, vitesse moyenne.
     * @param $arrayVehicule -> Array avec les détails d'un vehicule (taux_charge, autonomie, (immat)), en fonction d'un clien et d'un trajet.
     * @return bool -> le vehicule est apte a faire le trajet.
     */
    public static function calculTrajetVoiture($arrayTimeDistance,$arrayVehicule,$allerRetour){

        $autonomieVehicule = floatval($arrayVehicule['autonomie']);
        $tauxDeChargeVehicule = floatval($arrayVehicule['taux_charge'])/100;
        $autonomieRestanteVehicule = $autonomieVehicule*$tauxDeChargeVehicule;


        //Compare l'autonomie restante avec le trajet à faire et indique si le trajet ce fait ou non
        if ($allerRetour == true){
            if($autonomieRestanteVehicule-($arrayTimeDistance['distance']*2)>=$autonomieRestanteVehicule*0.10){
                return 'Le trajet est possible';
            }else if($autonomieRestanteVehicule-($arrayTimeDistance['distance']*2) <= $autonomieRestanteVehicule*0.10 and $autonomieRestanteVehicule-($arrayTimeDistance['distance']*2) >= $autonomieRestanteVehicule){
                return 'Le trajet est possible mais recharge fortement conseillée';
            }else if($autonomieRestanteVehicule-($arrayTimeDistance['distance']*2) <= 0){
                return 'le trajet n\'est pas possible sans recharge';
            }
        }else{
            if($autonomieRestanteVehicule-($arrayTimeDistance['distance'])>=$autonomieRestanteVehicule*0.10){
                return 'Le trajet est possible';
            }else if($autonomieRestanteVehicule-$arrayTimeDistance['distance'] <= $autonomieRestanteVehicule*0.10 and $autonomieRestanteVehicule-($arrayTimeDistance['distance']*2) >= $autonomieRestanteVehicule){
                return 'Le trajet est possible mais recharge fortement conseillée';
            }else if($autonomieRestanteVehicule-$arrayTimeDistance['distance'] <= 0){
                return 'le trajet n\'est pas possible sans recharge';
            }
        }
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return mixed - tous les vehicules en fonction d'un client.
     */
    public function getVehiculesByUser(Request $request, Response $response, array $args){

        $clients = R::findOne('clients', 'api_key= ? ', [$args['api_key']]);            //récupérer le id de la table client grace a la clé api.
        $vehicule = R::findAll('vehicule', 'clients_id='.$clients->getID());            //retupérer les vehicules grace au clients_id.
        return $response->withJson(['data'=> $vehicule]);
    }





    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return mixed - Renvoi si le vehicule est apte ou pas a faire le trajet.
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function main(Request $request, Response $response, array $args){

        $clients = R::findOne('clients', 'api_key= ? ', [$args['api_key']]);            //récupérer le id de la table client grace a la clé api.
        $vehicule = R::findOne('vehicule', ' marque= ? and nom= ? and clients_id='.$clients->getID(), [$args['marque'], $args['nom']]);         //retupérer un vehicule en fonction de la marque, du nom et d'un client.

            //Calcul du temps et de la distance grace a la methode getTimeAndDistanceFromCoord()
            $coordDepart = $args['long_depart'].','.$args['lat_depart'];
            $coordArrivee = $args['long_arrivee'].','.$args['lat_arrivee'];
            $getTimeDistance = $this->getTimeAndDistanceFromCoord($coordDepart, $coordArrivee);
            $date = new DateTime('NOW');

            // Ajouter un trajet dans la BDD avec RedBean.
            $insertTrajet = R::dispense('trajet');
            $insertTrajet->long_depart = $args['long_depart'];
            $insertTrajet->lat_depart = $args['lat_depart'];
            $insertTrajet->long_arrivee = $args['long_arrivee'];
            $insertTrajet->lat_arrivee = $args['lat_arrivee'];
            $insertTrajet->depart = $date->format('Y-m-d H:i:s');
            $insertTrajet->arrivee = $date->add(new DateInterval('PT'.intval($getTimeDistance['time']*3600).'S'))->format('Y-m-d H:i:s');
            $insertTrajet->nb_passagers = $args['nb_passagers'];
            $insertTrajet->vehicule_id = $vehicule->getID();
            $insertTrajet->distance = $getTimeDistance['distance'];
            $insertTrajet->temps = $getTimeDistance['time'];

            R::store($insertTrajet);

        //Retrouver un trajet en fonction des longitudes / latitudes et du nombre de passagers.
        $trajet = R::findOne('trajet', ' CAST(long_depart as DECIMAL)=CAST(? as DECIMAL) and CAST(lat_depart as DECIMAL)=CAST(? as DECIMAL)'
            .' and CAST(long_arrivee as DECIMAL)=CAST(? as DECIMAL) and CAST(lat_arrivee as DECIMAL)=CAST(? as DECIMAL) and nb_passagers= ? '
            , [$args['long_depart'],$args['lat_depart'],$args['long_arrivee'],$args['lat_arrivee'],$args['nb_passagers']]);

        //requeillir toutes les informations nécessaire à l'affichage des résultats attendus.
        $requete = R::getAll( 'select immat as immatriculation, nb_place as nombre_place, taux_charge , marque, nom from vehicule where clients_id='.$clients->getID().' and id='.$trajet['vehicule_id']);

        //En fonction des parametres, renvoi si le vehicule est apte ou pas.
        $return = $this->calculTrajetVoiture($getTimeDistance, $vehicule, $args['aller-retour']);

        //Ajouter la phrase définie audessus dans l'affichage finale.
        array_push($requete, $return);
        return $response->withJson([['data'=> $requete]]);



    }
}