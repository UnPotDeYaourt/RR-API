<?php

namespace App\Controller\EntityController;

use App\Entity\Favorite;
use App\Entity\Notification;
use App\Entity\NotificationType;
use App\Entity\User;
use App\Repository\FavoriteRepository;
use App\Repository\NotificationTypeRepository;
use App\Repository\RessourceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class FavoriteController extends AbstractController
{
    private NotificationType $notificationType;

    public function __construct(NotificationTypeRepository $notificationTypeRepository)
    {
        $this->notificationType = $notificationTypeRepository->findOneBy(['name' => 'favorite']);
    }

    #[OA\Tag(name: 'Favorite')]
    #[OA\Response(
        response: 200,
        description: 'Returns all favorite of one user',
        content: new Model(type: Favorite::class)
    )]
    #[Route('/api/favorite', name: 'all_favorite', methods: ['GET'])]
    public function getAllFavorite(Request $request,
                                   UserRepository $userRepository,
                                   FavoriteRepository $favoriteRepository,
                                   ): JsonResponse {
        $user = $this->getUser();
        $favorites = $favoriteRepository->findBy(['user_favorite' => $user]);

        return $this->json($favorites, 200, [], ['groups' => 'getFavorites']);
    }

    #[OA\Tag(name: 'Favorite')]
    #[OA\RequestBody(
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(
                    property: 'ressource_id',
                    type: 'integer',
                    example: 1
                ),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Returns the created favorite',
        content: new Model(type: Favorite::class)
    )]
    #[Route('/api/favorite', name: 'create_favorite', methods: ['POST'])]
    public function createFavorite(Request $request,
                                   UserRepository $userRepository,
                                   FavoriteRepository $favoriteRepository,
                                   RessourceRepository $resourceRepository,
                                   EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $resource_id = $data['ressource_id'];
        /** @var User $user */
        $user = $this->getUser();

        $favorite = $favoriteRepository->findOneBy(['user_favorite' => $user, 'ressource_favorite' => $resource_id]);

        if ($favorite) {
            $em->remove($favorite);
            $em->flush();
            return $this->json(null, 204);
        }

        $resource = $resourceRepository->findOneBy(['id' => $resource_id]);
        $resource_creator = $resource->getCreator();

        $favorite = new Favorite();
        $favorite->setUserFavorite($user);
        $favorite->setRessourceFavorite($resource);
        $em->persist($favorite);

        $notification_content = $user->getAccountName().' a ajouté votre ressource à ses favoris';

        $notification = Notification::create($user, $resource_creator, $this->notificationType, $notification_content, $resource);
        $em->persist($notification);

        $em->flush();

        return $this->json($favorite, 201, [], ['groups' => 'getFavorites']);
    }

    #[OA\Tag(name: 'Favorite')]
    #[OA\Parameter(name: 'id', description: 'Favorite id', in: 'path', required: true, example: 1)]
    #[OA\Response(
        response: 204,
        description: 'Delete a favorite',
    )]
    #[Route('/api/favorite/{id}', name: 'delete_favorite', methods: ['DELETE'])]
    public function deleteFavorite(Request $request,
                                   FavoriteRepository $favoriteRepository,
                                   EntityManagerInterface $em,
                                   $id): JsonResponse
    {
        $favorite = $favoriteRepository->findOneBy(['id' => $id]);
        $em->remove($favorite);
        $em->flush();

        return $this->json($favorite, 204, [], ['groups' => 'getFavorites']);
    }
}
