<?php
namespace App\Backend;

use \OCFram\Application;

class BackendApplication extends Application
{
  public function __construct()
  {
    parent::__construct();

    $this->name = 'Backend';
  }

  public function run()
  {
    if ($this->user->isAuthenticated())
    {
      $controller = $this->getController();
    }
    else
    {
      $controller = new Modules\Connexion\ConnexionController($this, 'Connexion', 'index');
    }
    //Si la vue correspondant à l’action à exécuter est en cache
    $cachedView = $this->cache()->readViewFromCache($this->name(), $controller->module(), $controller->view());
    if (NULL === $cachedView)
    {
        // Si la vue correspondant à l’action à exécuter n'est pas en cache,
        // alors on execute normalement le controleur
        $controller->execute();
        $cacheArray = $controller->createCache();
        
        // On génère la vue en dehors de la classe gérant la réponse
        // à envoyer au client
        $controller->page()->generatePage();
        if ( ((int) $cacheArray[$controller->view()]) > 0)
        {
            try
            {
                $controller->app()->cache()->writeViewToCache($controller->app()->name(), $controller->module(), $controller->view() , $controller->page()->generatedPage(), $cacheArray[$controller->view()]);
            }
            catch (\RuntimeException $ex)
            {
                // En cas d'erreur on affichera un message
                $this->user()->setFlash($ex->getMessage());
            }
        }
    }
    else
    {
        // Si la vue correspondant à l’action à exécuter est en cache,
        // alors le contrôleur correspondant ne doit pas être exécuté:
        // on passe directement la vue en cache à l’objet représentant la page
        $controller->page()->setGeneratedPage($cachedView);
       
    }
    // On envoie la vue à la classe gérant la réponse à envoyer au client,
    // afin qu’elle l’envoie à son tour au client.
    $this->httpResponse->setPage($controller->page());
    $this->httpResponse->send();
  }
}