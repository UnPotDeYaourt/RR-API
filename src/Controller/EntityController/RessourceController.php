<?php

namespace App\Controller\EntityController;

use App\Entity\Comment;
use App\Entity\ExploitedRessource;
use App\Entity\Favorite;
use App\Entity\Like;
use App\Entity\Media;
use App\Entity\Ressource;
use App\Entity\RessourceType;
use App\Entity\Statistic;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ExploitedRessourceRepository;
use App\Repository\FavoriteRepository;
use App\Repository\LikeRepository;
use App\Repository\RelationRepository;
use App\Repository\RelationTypeRepository;
use App\Repository\RessourceRepository;
use App\Repository\RessourceTypeRepository;
use App\Repository\StatisticsRepository;
use App\Repository\StatisticTypeRepository;
use App\Repository\UserRepository;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\AST\LikeExpression;
use Faker\Factory;
use Faker\Generator as FakerGenerator;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

class RessourceController extends AbstractController
{
    private LoggerInterface $logger;
    private FakerGenerator $faker;

    public function __construct(EntityManagerInterface $em, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->faker = Factory::create('fr_FR');
    }

    /**
     * This function allows us to create 20 ressources in database.
     */
    #[OA\Tag(name: 'Ressource')]
    #[Route('/api/ressources/create', name: 'create_ressources', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Create 20 ressources')]
    public function createRessources(RessourceRepository $ressourceRepository,
                                     UserRepository $repository,
                                     CategoryRepository $categoryRepository,
                                     RessourceTypeRepository $ressourceTypeRepository,
                                     EntityManagerInterface $manager,
                                     RelationTypeRepository $relationTypeRepository): JsonResponse
    {
        $users = $repository->findAll();
        $categories = $categoryRepository->findAll();
        $resource_types = $ressourceTypeRepository->findAll();
        $relationTypes = $relationTypeRepository->findAll();

        $ressources = [];
        for ($r = 0; $r < 50; ++$r) {
            $this_category_type = $categories[mt_rand(0, count($categories) - 1)];
            $this_resource_relationType = $relationTypes[mt_rand(0, count($relationTypes) - 1)];
            $ressource = new Ressource();
            $ressource->setDescription($this_resource_relationType->getName())
                ->setIsValid(true)
                ->setIsPublished(true)
                ->setCategory($this_category_type)
                ->setCreator($users[mt_rand(0, count($users) - 1)])
                ->setTitle($this_category_type->getName())
                ->setType($resource_types[mt_rand(0, count($resource_types) - 1)])
                ->addRelationType($this_resource_relationType);

            // Comments
            for ($c = 0; $c < mt_rand(0, 3); ++$c) {
                $comment = new Comment();
                $comment->setContent($this->faker->paragraph())
                    ->setCreator($users[mt_rand(0, count($users) - 1)])
                    ->setRessource($ressource)
                    ->setIsValid(true);

                $manager->persist($comment);
            }

            $mime_type = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/svg+xml',
                'pdf',
                'application/pdf',
                'mp4',
                'video/mp4',
                'video/quicktime'];
            /*// Media
            for ($m = 0; $m < mt_rand(0, 3); $m++)
            {
                $media = new Media();
                $media->setTitle($this->faker->word())
                    ->setTitle($this->faker->word())
                    ->setMimetype($mime_type[mt_rand(0, count($mime_type) - 1)])
                    ->setFilePath($this->faker->file('/var/www/github','/var/www/public/uploads/media' , false))
                    ->setRessource($ressource);

                $manager->persist($media);
            }*/

            $manager->persist($ressource);
            $ressources[] = $ressource;
        }
        for ($u = 0; $u < count($users) - 1; ++$u) {
            for ($r = 0; $r < count($ressources) - 1; ++$r) {
                if ((bool) mt_rand(0, 1)) {
                    $like = new Like();
                    $like->setUserLike($users[$u])
                        ->setRessourceLike($ressources[$r])
                        ->setIsLiked((bool) mt_rand(0, 1));

                    $favorite = new Favorite();
                    $favorite->setUserFavorite($users[$u])
                        ->setRessourceFavorite($ressources[$r]);

                    $manager->persist($favorite);
                    $manager->persist($like);
                }
            }
        }
        $manager->flush();

        return new JsonResponse('Ressources created', Response::HTTP_CREATED);
    }

    /**
     * This function allows us to get all ressources for public access.
     */
    #[Route('/api/public/resources', name: 'resources', methods: ['GET'])]
    #[OA\Tag(name: 'Ressource')]
    #[OA\Parameter(name: 'page', description: 'Page number', in: 'query', required: false, example: 1)]
    #[OA\Parameter(name: 'pageSize', description: 'Number of ressources per page', in: 'query', required: false, example: 10)]
    public function getAllRessources(RessourceRepository $repository,
                                     SerializerInterface $serializer,
                                     LikeRepository $likeRepository,
                                     Request $request,
                                     StatisticsRepository $statisticsRepository): JsonResponse
    {
        $resourceList = $repository->getAllPublicWithPagination($request->query->getInt('page', 1), $request->query->getInt('pageSize', 10));

        $jsonResourceList = $serializer->serialize($resourceList, 'json', ['groups' => 'getRessources']);

        return new JsonResponse($jsonResourceList, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to get all privates resources for user access from all type of relations.
     *
     * @throws Exception
     */
    #[OA\Tag(name: 'Ressource')]
    #[OA\Parameter(name: 'page', description: 'Page number', in: 'query', required: false, example: 1)]
    #[OA\Parameter(name: 'pageSize', description: 'Number of ressources per page', in: 'query', required: false, example: 10)]
    #[OA\Parameter(name: 'relation_type_id', description: 'Relation type id (used to sort resources by relation type)', in: 'query', required: false, example: 1)]
    #[OA\Parameter(name: 'category_id', description: 'Category id (used to sort resources by category), leave empty to make request without filter', in: 'query', required: false, example: 1)]
    #[Route('/api/resources', name: 'user_resources', methods: ['GET'])]
    public function getAllRelationsRessources(RessourceRepository $ressourceRepository,
                                              RelationRepository $relationRepository,
                                              CategoryRepository $categoryRepository,
                                              StatisticTypeRepository $statisticTypeRepository,
                                              LikeRepository $likeRepository,
                                              FavoriteRepository $favoriteRepository,
                                              RelationTypeRepository $relationTypeRepository,
                                              SerializerInterface $serializer,
                                              EntityManagerInterface $em,
                                              Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $user_id = $user->getId();



        $relation_type_id = $request->query->getInt('relation_type_id');
        $category_id = $request->query->getInt('category_id');

        $this->logger->info('Relation type id '.$relation_type_id);
        $this->logger->info('Category id '.$category_id);
        $this->logger->info('User id '.$user_id);
        $this->logger->info('Page '.$request->query->getInt('page', 1));
        $this->logger->info('Page size '.$request->query->getInt('pageSize', 10));

        // Get all resources from friends
        if (0 === $category_id && 1 === $relation_type_id) {
            $all_relations_resources = $ressourceRepository->getAllPublicResourcesByRelations($user_id);
        } elseif (0 != $category_id && 1 === $relation_type_id) {
            $all_relations_resources = $ressourceRepository->getAllPublicResourcesByRelationsByCategory($user_id, $category_id);
        } elseif(0 != $category_id && 0 != $relation_type_id) {
            $all_relations_resources = $ressourceRepository->getAllResourcesByRelationsByCategory($user_id, $relation_type_id, $category_id);
        } elseif (0 === $category_id && 0 != $relation_type_id) {
            $all_relations_resources = $ressourceRepository->getAllResourcesByRelationsType($user_id, $relation_type_id);
        } elseif (0 != $category_id && 0 === $relation_type_id) {
            $all_relations_resources = $ressourceRepository->getAllResourcesByCategoryWithourRelationType($user_id, $category_id);
        } else {
            $all_relations_resources = $ressourceRepository->getAllResourcesWithoutRelationTypeWithoutCategory($user_id);
        }

        $resources_id = [];
        $this->logger->info('Nombre de ressources : '.count($all_relations_resources));
        foreach ($all_relations_resources as $resource) {
            $resources_id[] = $resource['id'];
        }

        $friends_resources = $ressourceRepository->getAllWithPaginationById($resources_id, $request->query->getInt('page', 1), $request->query->getInt('pageSize', 10));
        $this->logger->info('Nombre de ressources après pagination : '.count($friends_resources));

        $resources_types = [];
        foreach ($friends_resources as $resource) {
            $like = $likeRepository->findOneBy(['user_like' => $user, 'ressource_like' => $resource]);
            $favorite = $favoriteRepository->findOneBy(['user_favorite' => $user, 'ressource_favorite' => $resource]);

            if($like) {
                $resource->setIsLiked(true);
            }
            if($favorite) {
                $resource->setIsFavorite(true);
            }

            $resources_types[] = $resource->getType();
        }

        if($category_id === 0) {
            $category = null;
        } else {
            $category = $categoryRepository->findOneBy(['id' => $category_id]);
        }

        if ($relation_type_id === 0) {
            $relation_type = null;
        } else {
            $relation_type = $relationTypeRepository->findOneBy(['id' => $relation_type_id]);
        }

        $statistic_type = $statisticTypeRepository->findOneBy(['name' => 'Consultation']);

        foreach ($resources_types as $type) {
            $statistic = Statistic::create($statistic_type, $relation_type, $type, $category);
            $em->persist($statistic);
        }
        $em->flush();


        $this->logger->info('Nombre de ressources : '.count($friends_resources));
        $jsonResourceList = $serializer->serialize($friends_resources, 'json', ['groups' => 'getRessources']);

        return new JsonResponse($jsonResourceList, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to get connected user's resources.
     */
    #[OA\Tag(name: 'Ressource')]
    #[OA\Response(response: 200, description: "Return user's ressources", content: new Model(type: Ressource::class))]
    #[Route('/api/resources/user_ressources', name: 'getRessourcesByUser', methods: ['GET'])]
    public function getUserRessources(SerializerInterface $serializer, RessourceRepository $repository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $ressources = $user->getRessources();
        $jsonRessources = $serializer->serialize($ressources, 'json', ['groups' => 'getRessources']);

        return new JsonResponse($jsonRessources, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to get connected user's drafts.
     */
    #[OA\Tag(name: 'Ressource')]
    #[Route('/api/resources/user_drafts', name: 'getDraftsByUser', methods: ['GET'])]
    #[OA\Response(response: 200, description: "Return user's drafts", content: new Model(type: Ressource::class))]
    public function getUserDrafts(SerializerInterface $serializer, RessourceRepository $repository): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $resources = $user->getRessources();

        $resources_drafts = [];

        foreach ($resources as $resource) {
            if (!$resource->isIsPublished()) {
                $resources_drafts[] = $resource;
            }
        }

        $jsonRessources = $serializer->serialize($resources_drafts, 'json', ['groups' => 'getRessources']);

        return new JsonResponse($jsonRessources, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to get all resources types.
     */
    #[Route('/api/resources/types', name: 'getRessourcesTypes', methods: ['GET'])]
    #[OA\Tag(name: 'Ressource')]
    #[OA\Response(response: 200, description: 'Return all ressources types', content: new Model(type: RessourceType::class))]
    public function getRessourcesTypes(SerializerInterface $serializer, RessourceTypeRepository $repository): JsonResponse
    {
        $ressourcesTypes = $repository->findAll();
        $jsonRessourcesTypes = $serializer->serialize($ressourcesTypes, 'json', ['groups' => 'getRessourcesTypes']);

        return new JsonResponse($jsonRessourcesTypes, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to add a resource exploited by a user.
     */
    #[OA\Tag(name: 'Ressource')]
    #[OA\Response(response: 201, description: 'Resource exploited by a user is created')]
    #[OA\RequestBody(description: 'Create a resource exploited by a user', required: true, attachables: [
        new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'resource_id', type: 'integer', example: 1),
                ]
            )
        ),
    ])]
    #[Route('/api/resources/exploited_resource', name: 'exploited_ressorce', methods: ['POST'])]
    public function add_exploited_resource(Request $request,
                                           ExploitedRessourceRepository $exploitedRessourceRepository,
                                           EntityManagerInterface $entityManager,
                                           RessourceRepository $ressourceRepository,
                                           SerializerInterface $serializer): JsonResponse
    {
        $content = $serializer->decode($request->getContent(), 'json');
        /** @var User $user */
        $user = $this->getUser();
        $resource_id = $content['resource_id'];

        $resource = $ressourceRepository->findOneBy(['id' => $resource_id]);

        $exploitedResource = ExploitedRessource::create($resource, $user);

        $entityManager->persist($exploitedResource);
        $entityManager->flush();

        return new JsonResponse('Exploited resource', Response::HTTP_CREATED);
    }

    /**
     * Remove the "exploited" status of a resource.
     */
    #[OA\Tag(name: 'Ressource')]
    #[OA\Response(response: 204, description: 'Remove the "exploited" status of a resource')]
    #[OA\RequestBody(description: 'Remove the "exploited" status of a resource', required: true, attachables: [
        new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'resource_id', type: 'integer', example: 1),
                ]
            )
        ),
    ])]
    #[Route('/api/resources/unexploited_resource', name: 'unexploited_ressorce', methods: ['DELETE'])]
    public function removeExploitedResource(Request $request,
                                            ExploitedRessourceRepository $exploitedRessourceRepository,
                                            EntityManagerInterface $entityManager,
                                            RessourceRepository $ressourceRepository,
                                            SerializerInterface $serializer): JsonResponse
    {
        $content = $serializer->decode($request->getContent(), 'json');
        /** @var User $user */
        $user = $this->getUser();
        $resource_id = $content['resource_id'];

        $resource = $ressourceRepository->findOneBy(['id' => $resource_id]);

        $exploitedResource = $exploitedRessourceRepository->findOneBy(['ressource' => $resource, 'citizen' => $user]);

        $entityManager->remove($exploitedResource);
        $entityManager->flush();

        return new JsonResponse('Unexploited resource', Response::HTTP_NO_CONTENT);
    }

    /**
     * This function allows us to get all resources public resources and relation resource for one user.
     *
     * @throws Exception
     */
    #[OA\Tag(name: 'Ressource')]
    #[OA\Parameter(name: 'research_user_id', description: 'The id of the user', in: 'query', required: true, example: 1)]
    #[OA\Parameter(name: 'page', description: 'Page number', in: 'query', required: false, example: 1)]
    #[OA\Parameter(name: 'pageSize', description: 'Number of ressources per page', in: 'query', required: false, example: 10)]
    #[Route('/api/resources/user', name: 'get_user_resources', methods: ['GET'])]
    public function getOneUserResources(RessourceRepository $ressourceRepository,
                                        Request $request,
                                        UserRepository $userRepository,
                                        SerializerInterface $serializer): JsonResponse
    {
        $page = $request->query->getInt('page', 1);
        $pageSize = $request->query->getInt('pageSize', 10);
        $research_user_id = $request->query->getInt('research_user_id', 1);

        /** @var User $user */
        $user = $this->getUser();
        $research_user = $userRepository->find($research_user_id);

        $resources_array = $ressourceRepository->getOneUserResources($user->getId(), $research_user->getId(), $page, $pageSize);

        $resources_id = [];
        foreach ($resources_array as $resource) {
            $resources_id[] = $resource['id'];
        }

        $resources = $ressourceRepository->getAllWithPaginationById($resources_id, $page, $pageSize);

        $jsonRessources = $serializer->serialize($resources, 'json', ['groups' => 'getRessources']);

        return new JsonResponse($jsonRessources, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to get one ressource by his id.
     */
    #[Route('/api/resources/{id}', name: 'oneRessource', methods: ['GET'])]
    #[OA\Tag(name: 'Ressource')]
    #[OA\Response(response: 200, description: 'Returns one ressource')]
    #[OA\Parameter(name: 'id', description: 'The id of the ressource', in: 'path', required: true, example: 1)]
    public function getOneRessource(Ressource $ressource, SerializerInterface $serializer): JsonResponse
    {
        $jsonRessource = $serializer->serialize($ressource, 'json', ['groups' => 'getRessources']);

        return new JsonResponse($jsonRessource, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to delete a ressource.
     */
    #[Route('/api/resources/{id}', name: 'deleteRessource', methods: ['DELETE'])]
    #[OA\Tag(name: 'Ressource')]
    #[OA\Response(response: 204, description: 'Delete one ressource')]
    #[OA\Parameter(name: 'id', description: 'The id of the ressource', in: 'path', required: true, example: 1)]
    public function deleteRessource(Ressource $ressource, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($ressource);
        $em->flush();

        return new JsonResponse('Ressource deleted', Response::HTTP_NO_CONTENT, [], true);
    }

    /**
     * This function allows us to create a ressource.
     */
    #[Route('/api/resources', name: 'addRessource', methods: ['POST'])]
    #[OA\Tag(name: 'Ressource')]
    #[OA\Response(response: 201, description: 'Create one ressource')]
    #[OA\RequestBody(description: 'Create a ressource', required: true, attachables: [
        new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Ressource title'),
                    new OA\Property(property: 'description', type: 'string', example: 'Ressource text content'),
                    new OA\Property(property: 'category_id', type: 'integer', example: 1),
                    new OA\Property(property: 'relation_type_id', type: 'integer', example: 1),
                ]
            )
        ),
    ])]
    public function addRessource(Request $request,
                                 SerializerInterface $serializer,
                                 EntityManagerInterface $em,
                                 UrlGeneratorInterface $urlGenerator,
                                 UserRepository $userRepository,
                                 RelationTypeRepository $relationTypeRepository,
                                 CategoryRepository $categoryRepository): JsonResponse
    {
        /* Creation de la ressource */
        $ressource = new Ressource();

        $content = json_decode($request->getContent(), true);

        /** @var User $user */
        $user = $this->getUser();
        $categoryId = $content['category_id'];
        $relation_type_id = $content['relation_type_id'];
        $ressource_title = $content['title'];

        $category = $categoryRepository->find($categoryId);

        $ressource->setCreator($user);
        $ressource->setTitle($ressource_title);
        $ressource->setCategory($category);
        $ressource->setIsPublished(false);
        $ressource->setDescription($content['description']);
        $ressource->setIsValid(false);
        if ($user) {
            $user->addRessource($ressource);
            $em->persist($user);
        }
        if ($category) {
            $category->addRessource($ressource);
            $em->persist($category);
        }
        if ($relation_type_id) {
            $relationType = $relationTypeRepository->find($relation_type_id);
        } else {
            $relationType = $relationTypeRepository->findBy(['name' => 'Public']);
        }
        $ressource->addRelationType($relationType);

        /* Upload */
        // TODO: Upload file at resource creation

        $em->persist($ressource);
        $em->flush();

        $jsonRessource = $serializer->serialize($ressource, 'json', ['groups' => 'getRessources']);

        $location = $urlGenerator->generate('oneRessource', ['id' => $ressource->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        return new JsonResponse($jsonRessource, Response::HTTP_CREATED, ['Location' => $location], true);
    }

    /**
     * This function allows us to update a ressource.
     */
    #[Route('/api/resources/{id}', name: 'updateRessource', methods: ['PUT'])]
    #[OA\Tag(name: 'Ressource')]
    #[OA\Response(response: 200, description: 'Update one ressource')]
    #[OA\Parameter(name: 'id', description: 'The id of the ressource', in: 'path', required: true, example: 1)]
    #[OA\RequestBody(description: 'Update a ressource', required: true, attachables: [
        new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'description', type: 'string', example: 'Ressource text content'),
                ]
            )
        ),
    ])]
    public function updateRessource(Ressource $ressource, Request $request, SerializerInterface $serializer, EntityManagerInterface $em): JsonResponse
    {
        $jsonRessource = $request->getContent();
        $updatedRessource = $serializer->deserialize($jsonRessource, Ressource::class, 'json', [AbstractNormalizer::OBJECT_TO_POPULATE => $ressource]);
        $em->persist($updatedRessource);
        $em->flush();

        return new JsonResponse('Ressource updated', Response::HTTP_OK);
    }
}
