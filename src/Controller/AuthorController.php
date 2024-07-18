<?php

namespace App\Controller;

use App\Entity\Author;
use App\Repository\AuthorRepository;
use App\Repository\BookRepository;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializationContext;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
// use Symfony\Component\Serializer\SerializerInterface;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use JMS\Serializer\Serializer;

class AuthorController extends AbstractController
{
    /**
    * Cette méthode permet de récupérer l'ensemble des auteurs.
    *
    * @OA\Response(
    *     response=200,
    *     description="Retourne la liste des auteurs",
    *     @OA\JsonContent(
    *        type="array",
    *        @OA\Items(ref=@Model(type=Author::class,groups={"getAuthors"}))
    *     )
    * )
    * @OA\Parameter(
    *     name="page",
    *     in="query",
    *     description="La page que l'on veut récupérer",
    *     @OA\Schema(type="int")
    * )
    *
    * @OA\Parameter(
    *     name="limit",
    *     in="query",
    *     description="Le nombre d'éléments que l'on veut récupérer",
    *     @OA\Schema(type="int")
    * )
    * @OA\Tag(name="Authors")
    *
    * @param AuthorRepository $authorRepository
    * @param SerializerInterface $serializer
    * @param Request $request
    * @return JsonResponse
    */
    #[Route('/api/author', name: 'author', methods: ['GET'])]
        public function getAllAuthors(AuthorRepository $authorRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 20);

        $idCache = "getAllAuthors-" .$page . "-" .$limit;

        $jsonAuthorList = $cache->get($idCache, function (ItemInterface $item) use ($authorRepository, $page, $limit, $serializer){
            $item->tag("authorsCache");

            $authorList = $authorRepository->findAllWithPagination($page, $limit);
            $context = SerializationContext::create()->setGroups(['getAuthors']);
            return $serializer->serialize($authorList, 'json', $context);
        });

        // $authorList = $authorRepository->findAll();
        return new JsonResponse($jsonAuthorList, Response::HTTP_OK,[], true);
    }
    /**
    * Cette méthode permet de rechercher un auteur par son ID.
    *
    * @OA\Response(
    *     response=200,
    *     description="Retourne un auteur",
    *     @OA\JsonContent(
    *        type="array",
    *        @OA\Items(ref=@Model(type=Author::class,groups={"getAuthors"}))
    *     )
    * )
    * 
    * @OA\Tag(name="Authors")
    *
    * @param Author $author
    * @param SerializerInterface $serializer
    * @return JsonResponse
    */
    #[Route('/api/author/{id}', name:'detailAuthor', methods: ['GET'])]
    public function getDetailBook(SerializerInterface $serializer, Author $author): JsonResponse
    {
        $context = SerializationContext::create()->setGroups(['getAuthors']);
        $context->setVersion("1.0");
        $jsonAuthor = $serializer->serialize($author ,'json', $context);
        return new JsonResponse($jsonAuthor, Response::HTTP_OK,[], true);
    }

/**
    * Cette méthode permet de supprimer un auteur par son ID.
    *
    * @OA\Response(
    *     response=200,
    *     description="Supprime un auteur",
    *     @OA\JsonContent(
    *        type="array",
    *        @OA\Items(ref=@Model(type=Author::class,groups={"getAuthors"}))
    *     )
    * )
    * 
    * @OA\Tag(name="Authors")
    *
    * @param Author $author
    * @return JsonResponse
    */
    #[Route ('/api/author/{id}', name: 'deleteAuthor', methods: ['DELETE'])]
    // #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un auteur')]
    public function deleteAuthor(Author $author, EntityManagerInterface $em, BookRepository $bookRepository, TagAwareCacheInterface $cachePool): JsonResponse
    {
        $cachePool->invalidateTags(["authorsCache"]);
        // Récupération de tous les livres de l'auteur spécifié.
        $books = $bookRepository->findBy(['author' => $author]);
        // Suppression de chaque livre de l'auteur.
        foreach ($books as $book) {
            $em->remove($book);
        }
        // Suppression de l'auteur de la base de données.
        $em->remove($author);
        // Application des changements dans la base de données.
        $em->flush();
        // Retour d'une réponse JSON indiquant que l'auteur a été supprimé.
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    /**
    * Cette méthode permet de créer un auteur.
    *
    * @OA\Response(
    *     response=200,
    *     description="Crée un auteur",
    *     @OA\JsonContent(
    *        type="array",
    *        @OA\Items(ref=@Model(type=Author::class,groups={"getAuthors"}))
    *     )
    * )
    *
    *  @OA\RequestBody(
    *     required=true,
    *     @OA\JsonContent(
    *         example={
    *             "firstName": "prénom",
    *             "lastName": "nom"
    *         },
    *         type="array",
    *        @OA\Items(ref=@Model(type=Author::class,groups={"getAuthors"}))
    *     )
    * )
    * @OA\Tag(name="Authors")
    *
    * @param SerializerInterface $serializer
    * @param EntityManagerInterface $em
    * @param UrlGeneratorInterface $urlGenerator
    * @param Request $request
    * @return JsonResponse
    */
    #[Route('/api/author', name:'createAuthor', methods: ['POST'])]
    // #[IsGranted('ROLE_ADMIN', message:'Vous n\'avez pas les droits suffisants pour créer un auteur')]
    public function createAuthor(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator, TagAwareCacheInterface $cachePool): JsonResponse
    {
    $author = $serializer->deserialize($request->getContent(), Author::class,'json');
    $errors = $validator->validate($author);
    if ($errors->count() > 0) {
        return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
    }
    $em->persist($author);
    $em->flush();
    // $content = $request->toArray();

    $context = SerializationContext::create()->setGroups(['getBooks']);
    $jsonAuthor = $serializer->serialize($author, 'json', $context);
    $location = $urlGenerator->generate('detailAuthor', ['id' => $author->getId()], urlGeneratorInterface::ABSOLUTE_URL);

    // $jsonAuthor = $serializer->serialize($author,'json', ['groups'=> 'getAuthors']);
    // $location = $urlGenerator->generate('detailAuthor', ['id' => $author->getId()], urlGeneratorInterface::ABSOLUTE_URL);

    return new JsonResponse($jsonAuthor, Response::HTTP_CREATED, ["Location" => $location], true);
    }
    /**
    * Cette méthode permet de modifier un auteur.
    *
    * @OA\Response(
    *     response=200,
    *     description="Modifie un auteur",
    *     @OA\JsonContent(
    *        type="array",
    *        @OA\Items(ref=@Model(type=Author::class,groups={"getAuthors"}))
    *     )
    * )
    *
    *  @OA\RequestBody(
    *     required=true,
    *     @OA\JsonContent(
    *         example={
    *             "firstName": "prénom",
    *             "lastName": "nom"
    *         },
    *         type="array",
    *        @OA\Items(ref=@Model(type=Author::class,groups={"getAuthors"}))
    *     )
    * )
    * @OA\Tag(name="Authors")
    *
    * @param SerializerInterface $serializer
    * @param EntityManagerInterface $em
    * @param UrlGeneratorInterface $urlGenerator
    * @param Request $request
    * @return JsonResponse
    */
    #[Route("/api/author/{id}", name:"updateAuthor", methods: ["PUT"])]
    // #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier un livre')]

    public function updateAuthor(Request $request, SerializerInterface $serializer, Author $currentAuthor, EntityManagerInterface $em, AuthorRepository $authorRepository, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    { 
    $newAuthor = $serializer->deserialize($request->getContent(), Author::class,'json');
    $currentAuthor->setFirstName($newAuthor->getFirstName());
    $currentAuthor->setLastName($newAuthor->getLastName());
    $errors = $validator->validate($currentAuthor);
        if (count($errors) > 0) {
            return new JsonResponse($serializer->serialize($errors, 'json'), Response::HTTP_BAD_REQUEST, [], true);
        }

    // $content = $request->toArray();
        // $idAuthor = $content['idAuthor'] ?? -1;
    // $currentAuthor->setAuthor($authorRepository->find($idAuthor));
    // $updatedAuthor = $serializer->deserialize($request->getContent(), Author::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $currentAuthor]);
    $em->persist($currentAuthor);
    $em->flush();
    $cache->invalidateTags(["authorsCache"]);
    return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}

