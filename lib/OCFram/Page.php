<?php
namespace OCFram;

class Page extends ApplicationComponent
{
  protected $contentFile;
  protected $vars = [];
  
  // attribut qui contient la page générée
  protected $generatedPage = [];

  public function addVar($var, $value)
  {
    if (!is_string($var) || is_numeric($var) || empty($var))
    {
      throw new \InvalidArgumentException('Le nom de la variable doit être une chaine de caractères non nulle');
    }

    $this->vars[$var] = $value;
  }

  // Méthode qui génère la page 
  // et qui stocke le resultat dans un attribut
  public function generatePage()
  {
    if (!file_exists($this->contentFile))
    {
      throw new \RuntimeException('La vue spécifiée n\'existe pas');
    }

    $user = $this->app->user();

    extract($this->vars);

    ob_start();
      require $this->contentFile;
    $content = ob_get_clean();

    ob_start();
      require __DIR__.'/../../App/'.$this->app->name().'/Templates/layout.php';
    $this->setGeneratedPage(ob_get_clean());
  }

  public function setContentFile($contentFile)
  {
    if (!is_string($contentFile) || empty($contentFile))
    {
      throw new \InvalidArgumentException('La vue spécifiée est invalide');
    }

    $this->contentFile = $contentFile;
  }
  
  // Setter de $generatedPage
  public function setGeneratedPage($content)
  {
      if (!is_string($content) || empty($content))
    {
      throw new \InvalidArgumentException('La contenu de la page est invalide');
    }
    $this->generatedPage = $content;
  }
  
  
  // Getter de $generatedPage
  public function generatedPage()
  {
      return $this->generatedPage;
  }
}