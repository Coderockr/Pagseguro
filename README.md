# How to use


## bootstrap.php

	<?php
	use Doctrine\ORM\Tools\Setup,
	    Doctrine\ORM\EntityManager,
	    Doctrine\Common\EventManager as EventManager,
	    Doctrine\ORM\Events,
	    Doctrine\ORM\Configuration,
	    Doctrine\Common\Cache\ArrayCache as Cache,
	    Doctrine\Common\Annotations\AnnotationRegistry, 
	    Doctrine\Common\Annotations\AnnotationReader,
	    Doctrine\Common\ClassLoader;

	$loader = require __DIR__.'/vendor/autoload.php';
	$loader->add('Skel', __DIR__.'/src');

	//doctrine
	$config = new Configuration();
	//$cache = new Cache();
	$cache = new \Doctrine\Common\Cache\ApcCache();
	$config->setQueryCacheImpl($cache);
	$config->setProxyDir('/tmp');
	$config->setProxyNamespace('EntityProxy');
	$config->setAutoGenerateProxyClasses(true);
	 
	//mapping (example uses annotations, could be any of XML/YAML or plain PHP)
	AnnotationRegistry::registerFile(__DIR__. DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'doctrine' . DIRECTORY_SEPARATOR . 'orm' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'Doctrine' . DIRECTORY_SEPARATOR . 'ORM' . DIRECTORY_SEPARATOR . 'Mapping' . DIRECTORY_SEPARATOR . 'Driver' . DIRECTORY_SEPARATOR . 'DoctrineAnnotations.php');

	\Doctrine\Common\Annotations\AnnotationRegistry::registerFile(
	    __DIR__ . '/vendor/jms/serializer/src/JMS/Serializer/Annotation/Type.php'
	);

	$driver = new Doctrine\ORM\Mapping\Driver\AnnotationDriver(
	    new Doctrine\Common\Annotations\AnnotationReader(),
	    array(__DIR__.'/src/Skel/Model')
	);
	$config->setMetadataDriverImpl($driver);
	$config->setMetadataCacheImpl($cache);


## app.php

	<?php
	require_once __DIR__.'/bootstrap.php';

	use Silex\Application,
    	Silex\Provider\DoctrineServiceProvider,
    	Symfony\Component\HttpFoundation\Request,
    	Dflydev\Silex\Provider\DoctrineOrm\DoctrineOrmServiceProvider;

	use Symfony\Component\HttpFoundation\Response;
	use Coderockr\SOA\RestControllerProvider;

	$app = new Application();

	//configuration
	$app->register(new Silex\Provider\SessionServiceProvider());

	//getting the EntityManager
	$app->register(new DoctrineServiceProvider, array(
	    'db.options' => array(
	        'driver' => 'pdo_mysql',
	        'host' => 'localhost',
	        'port' => '3306',
	        'user' => 'skel',
	        'password' => 'skel',
	        'dbname' => 'skel'
	    )
	));

	$app->register(new DoctrineOrmServiceProvider(), array(
	    'orm.proxies_dir' => '/tmp/' . getenv('APPLICATION_ENV'),
	    'orm.em.options' => array(
	        'mappings' => array(
	            array(
	                'type' => 'annotation',
	                'use_simple_annotation_reader' => false,
	                'namespace' => 'Skel\Model',
	                'path' => __DIR__ . '/src'
	            )
	        )
	    ),
	    'orm.proxies_namespace' => 'EntityProxy',
	    'orm.auto_generate_proxies' => true
	));

	$pagseguro = new PagseguroControllerProvider();
	$pagseguro->setToken('TOKEN');
	$pagseguro->setEmail('EMAIL');
	$pagseguro->setTransactionClass('Skel\Model\Transaction');
	$pagseguro->setBuyerClass('Skel\Model\User');
	//$pagseguro->setCouponClass('Skel\Model\Coupon');
	$pagseguro->setItemClass('Skel\Model\Product');
	$app->mount('/buy', $pagseguro);



# Interfaces

- Skel\Model\Transaction must implements Coderockr\Pagseguro\TransactionInterface

- Skel\Model\User must implements Coderockr\Pagseguro\BuyerInterface

- Skel\Model\Coupon must implements Coderockr\Pagseguro\CouponInterface
	
- Skel\Model\Product must implements Coderockr\Pagseguro\ItemInterface


# Pagseguro configuration

- Must generate a new Token in Integrações -> Token de segurança

- Must configure a url in Integrações -> Notificação de transações. Sample:

	http://server_url/buy/notification

