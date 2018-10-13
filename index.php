<?php
chdir(__DIR__);

require './vendor/autoload.php';
require './vendor/fzaninotto/faker/src/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use \RedBeanPHP\R as R;


R::setup('mysql:host=localhost;dbname=ecoway_v2','userapi','zMpBiaIsJ4K9mPb8'); //connect to data base.
$dataGenerator = Faker\Factory::create('fr_FR');
$configuration = [
    'settings' => [
        'displayErrorDetails' => true,
    ],
];
$c = new \Slim\Container($configuration);
$app = new \Slim\App($c);






/**
 * @TODO    A METTRE DANS L'IHM
 *
 * @param $coordDepart
 * @param $coordArrivee
 * @param $vehiculeId
 * @param $time
 * @param $nbPassagers
 * @return int
 * @throws Exception
 */
function ajoutTrajetBDD($coordDepart,$coordArrivee,$vehiculeId,$time,$nbPassagers){

    $arrayCoordDepart = explode(',',$coordDepart);
    $arrayCoordArrivee = explode(',',$coordArrivee);

    $xDepart = $arrayCoordDepart[0];
    $yDepart = $arrayCoordDepart[1];

    $xArrivee = $arrayCoordArrivee[0];
    $yArrivee = $arrayCoordArrivee[1];

    $date = new DateTime('NOW');
    $dateDepart = $date->format('Y-m-d H:i:s');
    $dateArrivee = $date->add(new DateInterval('PT'.intval($time*3600).'S'))->format('Y-m-d H:i:s');
    return R::exec("INSERT INTO trajet VALUES (NULL,$xDepart,$yDepart,$xArrivee,$yArrivee,'$dateDepart','$dateArrivee',$nbPassagers,$vehiculeId);");

}




/**
 * @TODO A METTRE DANS L'IHM
 *
 * @param $adresseDepart
 * @param $adresseArrivee
 * @return array
 */
function getCoordonnee($adresseDepart,$adresseArrivee){

    $urlDepart = 'https://api-adresse.data.gouv.fr/search/?q='.
        urlencode($adresseDepart);
    $urlArrivee = 'https://api-adresse.data.gouv.fr/search/?q='.
        urlencode($adresseArrivee);
    $dataDepart = json_decode(file_get_contents($urlDepart));
    $dataArrivee = json_decode(file_get_contents($urlArrivee));

    return $array = ['Depart' => $dataDepart->features[0]->geometry->coordinates[0].','.$dataDepart->features[0]->geometry->coordinates[1],
        'Arrivee' => $dataArrivee->features[0]->geometry->coordinates[0].','.$dataArrivee->features[0]->geometry->coordinates[1]];
}



$app->get('/{api_key}/vehicules', \Controllers\Vehicule::class.':getVehiculesByUser');

$app->get('/{api_key}/{marque}/{nom}/{long_depart}/{lat_depart}/{long_arrivee}/{lat_arrivee}/{nb_passagers}/{aller-retour}', \Controllers\Vehicule::class.':main');



$app->run();

