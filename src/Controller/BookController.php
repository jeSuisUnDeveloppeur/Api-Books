<?php

namespace App\Controller;

use App\Entity\Book;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use App\Service\VersionningService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class BookController extends AbstractController
{
    /**
     * Cette méthode permet de récupérer l'ensemble des livres.
     * 
     * @param BookRepository $bookRepository
     * @param SerializerInterface $serializer
     * @param Request $request
     * @return JsonResponse
     */
    #[Route('/api/books', name: 'books', methods: ['GET'])]
    #[OA\Response(
        response:200,
        description:"Retourne la liste des livres",
        content:new OA\JsonContent(
            type:'array',
            items:new OA\Items(ref: new Model(type:Book::class,groups:["getBooks"]))
        )
    )]
    #[OA\Parameter(
        name: 'page',
        in: 'query',
        description: "La page que l'on veut récupérer",
        schema: new OA\Schema(type: 'int')
    )]
    #[OA\Parameter(
        name: 'limit',
        in: 'query',
        description: "Le nombre d'éléments que l'on veut récupérer",
        schema: new OA\Schema(type: 'int')
    )]
    #[OA\Tag(name: 'Books')]
    public function getAllBooks(BookRepository $bookRepository, SerializerInterface $serializer,Request $request,TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page',1);
        $limit = $request->get('limit',3);

        $idCache = "getAllBooks-" . $page . "-" . $limit;
        
        $jsonBookList = $cache->get($idCache,function(ItemInterface $item) use ($bookRepository,$page,$limit,$serializer){
            $item->tag("booksCache");
            $bookList = $bookRepository->findAllWithPagination($page,$limit);
            $context = SerializationContext::create()->setGroups(['getBooks']);
            return $serializer->serialize($bookList,'json',$context);
        });

        return new JsonResponse($jsonBookList, Response::HTTP_OK, [], true);
    }
	
    /**
     * Cette méthode permet de récupérer un livre en particulier en fonction de son id. 
     *
     * @param Book $book
     * @param SerializerInterface $serializer
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name: 'detailBook', methods: ['GET'])]
    public function getDetailBook(Book $book, SerializerInterface $serializer,VersionningService $versionningService): JsonResponse {
        $version = $versionningService->getVersion();
        $context = SerializationContext::create()->setGroups(['getBooks']);
        $context->setVersion($version);
        $jsonBook = $serializer->serialize($book, 'json', $context);
        return new JsonResponse($jsonBook, Response::HTTP_OK, [], true);
    }
    
    
    /**
     * Cette méthode permet de supprimer un livre par rapport à son id. 
     *
     * @param Book $book
     * @param EntityManagerInterface $em
     * @return JsonResponse 
     */
    #[Route('/api/books/{id}', name: 'deleteBook', methods: ['DELETE'])]
    public function deleteBook(Book $book, EntityManagerInterface $em,TagAwareCacheInterface $cache): JsonResponse {
        $cache->invalidateTags(["booksCache"]);
        $em->remove($book);
        $em->flush();
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Cette méthode permet d'insérer un nouveau livre. 
     * Exemple de données : 
     * {
     *     "title": "Le Seigneur des Anneaux",
     *     "coverText": "C'est l'histoire d'un anneau unique", 
     *     "idAuthor": 5
     * }
     * 
     * Le paramètre idAuthor est géré "à la main", pour créer l'association
     * entre un livre et un auteur. 
     * S'il ne correspond pas à un auteur valide, alors le livre sera considéré comme sans auteur. 
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param EntityManagerInterface $em
     * @param UrlGeneratorInterface $urlGenerator
     * @param AuthorRepository $authorRepository
     * @return JsonResponse
     */
    #[Route('/api/books', name:"createBook", methods: ['POST'])] 
    #[IsGranted("ROLE_ADMIN",message:"Vous n'avez pas les droits suffisant pour créer un livre")]
    public function createBook(Request $request, SerializerInterface $serializer, EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator, AuthorRepository $authorRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse {

        $cache->invalidateTags(["booksCache"]);
        $book = $serializer->deserialize($request->getContent(), Book::class, 'json');
        $errors = $validator->validate($book);
        if($errors->count() > 0){
            return new JsonResponse($serializer->serialize($errors,'json'),JsonResponse::HTTP_BAD_REQUEST,[],true);
        }
        $em->persist($book);
        $em->flush();

        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $book->setAuthor($authorRepository->find($idAuthor));

        $context = SerializationContext::create()->setGroups(['getBooks']);
		$jsonBook = $serializer->serialize($book, 'json', $context);
		
        $location = $urlGenerator->generate('detailBook', ['id' => $book->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

		return new JsonResponse($jsonBook, Response::HTTP_CREATED, ["Location" => $location], true);	
    }
    
    
    /**
     * Cette méthode permet de mettre à jour un livre en fonction de son id. 
     * 
     * Exemple de données : 
     * {
     *     "title": "Le Seigneur des Anneaux",
     *     "coverText": "C'est l'histoire d'un anneau unique", 
     *     "idAuthor": 5
     * }
     *
     * @param Request $request
     * @param SerializerInterface $serializer
     * @param Book $currentBook
     * @param EntityManagerInterface $em
     * @param AuthorRepository $authorRepository
     * @return JsonResponse
     */
    #[Route('/api/books/{id}', name:"updateBook", methods:['PUT'])]
    #[IsGranted('ROLE_ADMIN',message:"Vous n'avez pas les droits suffisants pour éditer un livre")]
    public function updateBook(Request $request, SerializerInterface $serializer,
    Book $currentBook, EntityManagerInterface $em, AuthorRepository $authorRepository,TagAwareCacheInterface $cache,ValidatorInterface $validator): JsonResponse 
    {
        $newBook = $serializer->deserialize($request->getContent(),Book::class,"json");
        $currentBook->setTitle($newBook->getTitle());
        $currentBook->setCoverText($newBook->getCoverText());

        $errors = $validator->validate($currentBook);
        if($errors->count() > 0){
            return new JsonResponse($serializer->serialize($errors,'json'),JsonResponse::HTTP_BAD_REQUEST,[],true);
        }
        
        $content = $request->toArray();
        $idAuthor = $content['idAuthor'] ?? -1;

        $currentBook->setAuthor($authorRepository->find($idAuthor));

        $em->persist($currentBook);
        $em->flush();
        
        $cache->invalidateTags(["booksCache"]);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }


}