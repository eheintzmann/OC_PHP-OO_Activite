<?php
namespace App\Backend\Modules\News;

use \OCFram\BackController;
use \OCFram\HTTPRequest;
use \Entity\News;
use \Entity\Comment;
use \FormBuilder\CommentFormBuilder;
use \FormBuilder\NewsFormBuilder;
use \OCFram\FormHandler;

class NewsController extends BackController
{
  public function executeDelete(HTTPRequest $request)
  {
    $newsId = $request->getData('id');
    $flashMessage = '';
    
    //Suppression de la news de la BDD
    $this->managers->getManagerOf('News')->delete($newsId);
    
    // On efface la news du cache de données
    try
    {
        $this->app()->cache()->deleteDatasFromCache('News', $newsId);
    }
    catch (\RuntimeException $ex)
    {
        // En cas d erreur on definit un message
        $flashMessage .= 'ERREUR: La news n\'a pas pu ètre effaçée du cache ne données';
    }
    
    // Suppression des commentaires correspondants à la news dans la BDD
    $this->managers->getManagerOf('Comments')->deleteFromNews($newsId);

    // on efface la liste des commentaires correspondants du cache de données
    try
    {
        $this->app()->cache()->deleteDatasFromCache('Comments', $newsId);
    }
    catch (\RuntimeException $ex)
    {
        // En cas d erreur on définit un message
        $flashMessage .= 'ERREUR: Les commentaires n\'ont pas pu ètre effaçés du cache ne données';
    }
    
    // Si une news a été supprimée,
    // on efface la vue correspondante du cache
    try
    {
      $this->app()->cache()->deleteViewFromCache('Frontend_News_index');
    } 
    catch (\RuntimeException $ex)
    {
      $this->app()->user()->setFlash($ex->getMessage());
    }
    
    $this->app->user()->setFlash($flashMessage.'La news a bien été supprimée !');
    
    $this->app->httpResponse()->redirect('.');
  }

  public function executeDeleteComment(HTTPRequest $request)
  {
    $flashMessage = '';
    // On récupère l'Id de la news à laquelle le commentaire est rattaché
    $newsId = $this->managers->getManagerOf('Comments')->get($request->getData('id'))->news();
      
     // Suppression du commentaire dans la BDD,
     $this->managers->getManagerOf('Comments')->delete($request->getData('id'));
     
    // on efface la liste des commentaires correspondants du cache
    try
    {
        $this->app()->cache()->deleteDatasFromCache('Comments', $newsId);
    }
    catch (\RuntimeException $ex)
    {
        // En cas d erreur on définit un message
        $flashMessage .= 'ERREUR: Le commentaire n\'a pas pu ètre effaçé du cache de données';
    }
    
    $this->app->user()->setFlash($flashMessage.'Le commentaire a bien été supprimé !');
    
    $this->app->httpResponse()->redirect('.');
  }

  public function executeIndex(HTTPRequest $request)
  {
    $this->page->addVar('title', 'Gestion des news');

    $manager = $this->managers->getManagerOf('News');

    $this->page->addVar('listeNews', $manager->getList());
    $this->page->addVar('nombreNews', $manager->count());
  }

  public function executeInsert(HTTPRequest $request)
  {
    $this->processForm($request);

    $this->page->addVar('title', 'Ajout d\'une news');
  }

  public function executeUpdate(HTTPRequest $request)
  {
    $this->processForm($request);
      
    $this->page->addVar('title', 'Modification d\'une news');
  }

  public function executeUpdateComment(HTTPRequest $request)
  {
    $this->page->addVar('title', 'Modification d\'un commentaire');

    if ($request->method() == 'POST')
    {
      $comment = new Comment([
        'id' => $request->getData('id'),
        'auteur' => $request->postData('auteur'),
        'contenu' => $request->postData('contenu')
      ]);
    }
    else
    {
      $comment = $this->managers->getManagerOf('Comments')->get($request->getData('id'));
    }

    $formBuilder = new CommentFormBuilder($comment);
    $formBuilder->build();

    $form = $formBuilder->form();

    $formHandler = new FormHandler($form, $this->managers->getManagerOf('Comments'), $request);

    if ($formHandler->process())
    {
      $this->app->user()->setFlash('Le commentaire a bien été modifié');
      
      // On efface du cache la liste des commentaires correspondant à la news
      try
      {
        $this->app()->cache()->deleteDatasFromCache('Comments', $this->managers->getManagerOf('Comments')->get($request->getData('id'))->news());
      }
      catch (RuntimeException $ex)
      {
        // En cas d erreur on affichera un message
        $this->app->user()->setFlash('Le commentaire a bien été modifié. ERREUR: Le commentaire n\'a pas pu ètre effaçé du cache de données');
      }


      $this->app->httpResponse()->redirect('/admin/');
    }

    $this->page->addVar('form', $form->createView());
  }

  public function processForm(HTTPRequest $request)
  {
    if ($request->method() == 'POST')
    {
      $news = new News([
        'auteur' => $request->postData('auteur'),
        'titre' => $request->postData('titre'),
        'contenu' => $request->postData('contenu')
      ]);

      if ($request->getExists('id'))
      {
        $news->setId($request->getData('id'));
      }
    }
    else
    {
      // L'identifiant de la news est transmis si on veut la modifier
      if ($request->getExists('id'))
      {
        $news = $this->managers->getManagerOf('News')->getUnique($request->getData('id'));
      }
      else
      {
        $news = new News;
      }
    }

    $formBuilder = new NewsFormBuilder($news);
    $formBuilder->build();

    $form = $formBuilder->form();

    $formHandler = new FormHandler($form, $this->managers->getManagerOf('News'), $request);

    if ($formHandler->process())
    {
      $this->app->user()->setFlash($news->isNew() ? 'La news a bien été ajoutée !' : 'La news a bien été modifiée !');
      
      //Si la news a été modifiée, on l'efface du cache
      if (!$news->isNew())
      {
        try  
        {
            $this->app()->cache()->deleteDatasFromCache('News' , $request->getData('id'));
        }
        catch (RuntimeException $ex)
        {
          // En cas d erreur on affichera un message
          $this->app->user()->setFlash('La news a bien été modifiée ! ERREUR: La news n\'a pas pu être effaçée du cache de données');
        }
      }
      
      // Si une news a été ajoutée ou modifié,
      // on efface la vue correspondante du cache
      try
      {
        $this->app()->cache()->deleteViewFromCache('Frontend_News_index');
      } 
      catch (\RuntimeException $ex)
      {
        $this->app()->user()->setFlash($ex->getMessage());
      }
      
      $this->app->httpResponse()->redirect('/admin/');
    }

    $this->page->addVar('form', $form->createView());
  }
  
  public function createCache()
  {
    // On retourne un tableau de la forme ['nomdelavue' => 'duree'].
    // La durée est exprimée en secondes,
    // et se règle depuis le fichier de configuration de l 'application.
    return [  'index'           => $this->app->config()->get('index_caching_time'),
              'delete'          => $this->app->config()->get('delete_caching_time'),
              'deleteComment'   => $this->app->config()->get('delete_comment_caching_time'),
              'insert'          => $this->app->config()->get('insert_caching_time'),
              'update'          => $this->app->config()->get('update_caching_time'),
              'updateComment'   => $this->app->config()->get('updateComment_caching_time')
            ];
  }
}