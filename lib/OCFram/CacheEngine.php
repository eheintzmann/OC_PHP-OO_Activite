<?php

namespace OCFram;

class CacheEngine extends ApplicationComponent
{
    // Temps d'expiration des données en cache
    const DATAS_CACHING_TIME = 30;

    // Dossier de stockage des données
    const DATAS_CACHE_DIR = '/tmp/cache/datas';
    
    // Dossier de stockage des vues
    const VIEWS_CACHE_DIR = '/tmp/cache/views';

    public function __construct(Application $app)
    {
        parent::__construct($app);
        // On crée les dossiers de cache si ils n'existent pas.
        if (! is_dir(__DIR__.'/../..'.self::DATAS_CACHE_DIR))
        {
            mkdir(__DIR__.'/../..'.self::DATAS_CACHE_DIR, 0777, TRUE);
        }
        if (! is_dir(__DIR__.'/../..'.self::VIEWS_CACHE_DIR))
        {
            mkdir(__DIR__.'/../..'.self::VIEWS_CACHE_DIR, 0777, TRUE);
        }
    }

    // Méthode statique qui verifie si les données en cache ont expirées
    public static function isExpired($timestamp)
    {
	$check = (is_int($timestamp) || is_float($timestamp))
		? $timestamp
		: (string) (int) $timestamp;
	
        // On verifie si le format du timestamp est valide
        if (
            $check === $timestamp
            && (int) $timestamp <=  PHP_INT_MAX
            && (int) $timestamp >= ~PHP_INT_MAX
            )
        {
            // si le timestamp est valide on le compare a la date actuelle
            return ( (int) $timestamp <= (int) time() );
        }
        else
        {
            // si le timestamp n est pas valide,
            // on le considére comme expiré
            return TRUE;
        }
    }

    // Méthode qui vérifie si un fichier de cache existe,
    // et renvoie les données qu'il contient, si elles n'ont pas expiré.
    protected function loadFile($filepath)
    {
        // On vérifie que le fichier existe
        if (!file_exists($filepath))
        {
            return NULL;
        }
        
        // On ouvre le fichier
        $file_handle = fopen($filepath,'rb');
        if (!$file_handle)
        {
            throw new \RuntimeException('Erreur lors de l\'ouverture du fichier '.realpath($filepath));
        }

        // On récupère  le timestamp nettoyé (en première position)
        $timestamp = substr(fgets($file_handle), 0 ,10);
        
        // On vérifie le timestamp
        if ($this->isExpired($timestamp))
        {
            // Si le cache a expiré
            // on ferme le fichier de cache proprement
            if (!fclose( $file_handle ))
            {
                throw new \RuntimeException('Erreur lors de la fermeture du fichier '.realpath($filepath));
            }
            
            // puis efface le fichier de cache
            if (!unlink($filepath))
            {
               throw new \RuntimeException('Erreur lors de l\'effacement du fichier '.$filepath);
            }
            
            return NULL;
        }
                
        $contentStr = '';
        
        // On parcourt le fichier jusqu'à la fin, et on recupère son contenu
        while($temp = fgets($file_handle))
        {
            $contentStr .= $temp;
        }
        
        // On ferme proprement le fichier
        if (!fclose( $file_handle ))
        {
            throw new \RuntimeException('Erreur lors de la fermeture du fichier '.realpath($filepath));
        }
        
        // On retourne le contenu récupéré (sans le timestamp)
        return $contentStr;
    }
    
    
    // Méthode qui sauvegarde une chaine de caratères dans un fichier de cache
    // ainsi qu'un date date d'expiration.
    protected function saveFile( $filepath, $str, $cachingTime)
    {
        // Si la chaine de caractères est vide, on ne sauvegarde rizn
        if (empty($str))
        {
            return NULL;
        }

        // On ouvre (ou on le crée) le fichier correspondant aux données à mettre en cache
        $file_handle = fopen($filepath,'wb');
        if (!$file_handle )
        {
            throw new \RuntimeException('Erreur lors de l\'ouverture/création du fichier '.realpath($filepath));
        }

        // On écrit le timestamp dans le fichier ( en première position )
        if (!fputs( $file_handle, strtotime( '+'.$cachingTime.' seconds' )."\n"))
        {
            throw new \RuntimeException('Erreur lors de l\'écriture du fichier '.realpath($filepath));
        }

        // On sauve l'objet dans le fichier (en seconde position)
        if (!fputs( $file_handle, $str))
        {
            throw new \RuntimeException('Erreur lors de l\'écriture du fichier '.realpath($filepath));
        }
        
        // On ferme proprement le fichier
        if (!fclose( $file_handle ))
        {
            throw new \RuntimeException("Erreur lors de la fermeture du fichier ".realpath($filepath));
        }

        // on quitte proprement la méthode
        return NULL;
    }

  // Méthode qui charge des données depuis le cache
    public function readDatasFromCache($name, $id)
    {
        // On verifie la validité des arguments
        if ( empty($name) || !is_string($name) || empty($id) || !is_string($id) )
        {
            return NULL;
        }
        
        // On charge le fichier correpondants au données en cache
        $tempStr = $this->loadFile(__DIR__.'/../..'.self::DATAS_CACHE_DIR.'/'.$name.'_'.$id);
        
        // On retourne les données desérialisées (si il y en a)
        if (!empty($tempStr))
        {
            $unserialized = unserialize($tempStr);
            if ($unserialized === FALSE)
            {
                return NULL;
            }
            else
            {
                return $unserialized;
            }
        }
        else
        {
            return NULL;
        }
    }

  // Méthode qui écrit des données en cache
    public function writeDatasToCache($name, $id, $object)
    {
        // On verifie la validité des arguments
        if ( empty($name) || !is_string($name) )
        {
            throw new \InvalidArgumentException('Le nom du fichier est invalide');
        }
        if ( empty($id) || !is_string($id) )
        {
            throw new \InvalidArgumentException('L\'identifiant du fichier est invalide');
        }
        if (is_null($object))
        {
            return null;
        }
        
        // On serialise l'objet passé en argument
        $str = serialize($object);

        // On sauvegarde les donneés sérialisées dans un fichier de cache.
        $this->saveFile(__DIR__.'/../..'.self::DATAS_CACHE_DIR.'/'.$name.'_'.$id, $str, self::DATAS_CACHING_TIME);
    }

  // Méthode qui supprime des données mises en cache
    public function deleteDatasFromCache( $name, $id)
    {
        // On verifie la validité des arguments
       if ( empty($name) || !is_string($name) )
        {
            throw new \InvalidArgumentException('Le nom du fichier est invalide');
        }
        if ( empty($id) || !is_string($id) )
        {
            throw new \InvalidArgumentException('L\'identifiant du fichier est invalide');
        }
        
        $filepath = __DIR__.'/../..'.self::DATAS_CACHE_DIR.'/'.$name.'_'.$id;
        
        // Si un fichier correspond aux données a ôter du cache de données existe,
        // on le détruit.
        if (file_exists($filepath))
        {
            if (!unlink($filepath))
            {
               throw new \RuntimeException('Erreur lors de l\'effacement du fichier '.$filepath);
            }
        }
        
        // On quitte la méthode proprement
        return NULL;
    }
    
    // Methode qui lit une vue depuis le cache
    public function readViewFromCache($name, $module, $action)
    {
        // On vérifie la validité des arguments
        if ( empty($name) || empty($module) || empty($action) 
             || !is_string($name) || !is_string($module) || !is_string($action) )
        {
            return NULL;
        }
        
        $content= '';
        
        // On récupère le contenu du fichier correspondant à la vue mise en cache,
        // et on le retourne
        try
        {
            $content = $this->loadFile(__DIR__.'/../..'.self::VIEWS_CACHE_DIR.'/'.$name.'_'.$module.'_'.$action);
        }
        catch (\RuntimeException $ex)
        {
            $this->app()->user()->setFlash($ex->getMessage());
        }
        
        return $content;
    }
    
    // Méthode qui mets en cache une vue.
    public function writeViewToCache($appName, $moduleName, $viewName, $viewToCache, $cachingTime )
    {
        // On vérifie la validité des arguments
        if ( empty($appName) || empty($moduleName) || empty($viewName) 
             || !is_string($appName) || !is_string($moduleName) || !is_string($viewName) )
        {
            throw new \InvalidArgumentException('Les arguments doivent être des chaînes de catatères valides');
        }
        try
        {
            $this->saveFile(__DIR__.'/../..'.self::VIEWS_CACHE_DIR.'/'.$appName.'_'.$moduleName.'_'.$viewName, $viewToCache, $cachingTime);
        } 
        catch (\RuntimeException $ex)
        {
            $this->app()->user()->setFlash($ex->getMessage());
            return NULL;
        }
        return $cachingTime;        
    }
    
    // Methode qui supprime une vue du cache
    public function deleteViewFromCache($name)
    {
        // On verifie la validité des arguments
       if ( empty($name) || !is_string($name) )
        {
            throw new \InvalidArgumentException('Le nom du fichier est invalide');
        }
        $filepath = __DIR__.'/../..'.self::VIEWS_CACHE_DIR.'/'.$name;
        
        // Si un fichier correspond a la vue à ôter du cache existe,
        // on le détruit.
        if (file_exists($filepath))
        {
            if (!unlink($filepath))
            {
               throw new \RuntimeException('Erreur lors de l\'effacement du fichier '.$filepath);
            }
        }
        
        // On quitte la méthode proprement
        return NULL;
    }
}
?>
