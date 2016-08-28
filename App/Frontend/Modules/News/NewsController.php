<?php
namespace App\Frontend\Modules\News;

use \OCFram\BackController;
use \OCFram\HTTPRequest;
use \Entity\Comment;
use \FormBuilder\CommentFormBuilder;
use \OCFram\FormHandler;

class NewsController extends BackController
{
  public function executeIndex(HTTPRequest $request)
  {
    $nombreNews = $this->app->config()->get('nombre_news');
    $nombreCaracteres = $this->app->config()->get('nombre_caracteres');
    
    // On ajoute une définition pour le titre.
    $this->page->addVar('title', 'Liste des '.$nombreNews.' dernières news');
    
    // On récupère le manager des news.
    $manager = $this->managers->getManagerOf('News');
    
    $listeNews = $manager->getList(0, $nombreNews);
    
    foreach ($listeNews as $news)
    {
      if (strlen($news->contenu()) > $nombreCaracteres)
      {
        $debut = substr($news->contenu(), 0, $nombreCaracteres);
        $debut = substr($debut, 0, strrpos($debut, ' ')) . '...';
        
        $news->setContenu($debut);
      }
    }
    
    // On ajoute la variable $listeNews à la vue.
    $this->page->addVar('listeNews', $listeNews);            
  }
  
  public function executeShow(HTTPRequest $request)
  {
    $flashMessage = ''; 
    
    //  on tente de charger la news depuis le cache de données
    $news = $this->app()->cache()->readDatasFromCache('News', $request->getData('id'));
    if ($news === NULL)
    {
        // Si la news n'est pas à jour OU n'est pas dans le cache,
        // on les charge depuis la base de données
        $news = $this->managers->getManagerOf('News')->getUnique($request->getData('id'));
      
        // Puis on mets la news en cache
        try
        {
            $this->app()->cache()->writeDatasToCache('News', $request->getData('id'), $news);            
        } 
        catch (\RuntimeException $ex)
        {
            // En cas d erreur on definit un message
            $flashMessage = "ERREUR: La news n'a pas pu être mise en cache"            ;
        }
    }
    else
    {
        // Si la news a été chargées depuis le cache de données, on définit un message
        $flashMessage .= 'La news a été chargée depuis le cache de données.';
    }
    
    if (empty($news))
    {
        $this->app->httpResponse()->redirect404();
    }
      
    $this->page->addVar('title', $news->titre());
    $this->page->addVar('news', $news);
    
    // On tente de charger les commentaires depuis le cache de données
    $comments = $this->app()->cache()->readDatasFromCache('Comments', $news->id());
    if ($comments === NULL)
    {
        // Si les commentaires ne sont pas en cache OU pas a jour
        // on les charge depuis la base de données
        $comments = $this->managers->getManagerOf('Comments')->getListOf($news->id());

        //Puis on les mets en cache
        try
        {
            $this->app()->cache()->writeDatasToCache('Comments', $news->id(), $comments);
        }
        catch (RuntimeException $ex)
        {
            // En cas d erreur on definit un message
            $flashMessage = "ERREUR: Les commentaires n'ont pas pu être mis en cache"; 
        }
    }
    else 
    {
        // Si les commentaires ont été chargés depuis le cache de données, on définit un message
        $flashMessage .= 'La liste des commentaires a été chargée depuis le cache de données.';
    }
    // Enfin on ajoute les commentaires à la vue
    $this->page->addVar('comments', $comments);
    
    // On affichera un message prédedemment defini (non vide)
    if ($flashMessage !== '')
    {
      $this->app->user()->setFlash($flashMessage);
    }
  }

  public function executeInsertComment(HTTPRequest $request)
  {
    // Si le formulaire a été envoyé.
    if ($request->method() == 'POST')
    {
      $comment = new Comment([
        'news' => $request->getData('news'),
        'auteur' => $request->postData('auteur'),
        'contenu' => $request->postData('contenu')
      ]);
    }
    else
    {
      $comment = new Comment;
    }

    $formBuilder = new CommentFormBuilder($comment);
    $formBuilder->build();

    $form = $formBuilder->form();

    $formHandler = new FormHandler($form, $this->managers->getManagerOf('Comments'), $request);

    if ($formHandler->process())
    {
      $this->app->user()->setFlash('Le commentaire a bien été ajouté, merci !');

      // si un commentaire a été ajouté,
      // on efface les commentaires du cache de données
      try
      {
        $this->app()->cache()->deleteDatasFromCache('Comments', $request->getData('news'));
      }
      catch (RuntimeException $ex)
      {
        // En cas d'erreur on affichera un message
        $this->app->user()->setFlash('ERREUR: Les commentaires n\ont pas pu ètre effaçés du cache ne données');
      }
      
      $this->app->httpResponse()->redirect('news-'.$request->getData('news').'.html');
    }

    $this->page->addVar('comment', $comment);
    $this->page->addVar('form', $form->createView());
    $this->page->addVar('title', 'Ajout d\'un commentaire');
  }
  
  public function createCache()
  {
    // On retourne un tableau de la forme ['nomdelavue' => 'duree'].
    // La durée est exprimée en secondes,
    // et se règle depuis le fichier de configuration de l 'application.
    return [ 'index' => $this->app->config()->get('index_caching_time'),
              'show' => $this->app->config()->get('show_caching_time'),
              'insertComment' => $this->app->config()->get('insert_comment_caching_time')
            ];
  }
}