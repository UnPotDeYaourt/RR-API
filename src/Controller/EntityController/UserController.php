<?php

namespace App\Controller\EntityController;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class UserController extends AbstractController
{
    /**
     * This function allows us to get all users.
     */
    #[Route('/api/users', name: 'users', methods: ['GET'])]
    #[OA\Tag(name: 'User')]
    public function getAllUsers(UserRepository $userRepository, SerializerInterface $serializer, Request $request): JsonResponse
    {
        $userList = $userRepository->findAll();
        $jsonUserList = $serializer->serialize($userList, 'json', ['groups' => 'getUsers']);

        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to get the user profile.
     */
    #[Route('/api/profile', name: 'profile', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Returns the user data', content: new Model(type: User::class))]
    #[OA\Tag(name: 'User')]
    #[Security(name: 'Bearer')]
    public function getProfile(SerializerInterface $serializer): JsonResponse
    {
        $user = $this->getUser();
        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getUsers']);

        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to search a user by his name.
     */
    #[Route('/api/users/search', name: 'searchUser', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Returns the finded user', content: new Model(type: User::class))]
    #[OA\Tag(name: 'User')]
    #[OA\Parameter(name: 'search', description: 'The name of the user', in: 'query', required: true, example: 'John',)]
    public function searchUser(UserRepository $userRepository, SerializerInterface $serializer, Request $request): JsonResponse
    {
        $search = $request->query->get('search', default: '');
        $userList = $userRepository->searchUser($search);
        $jsonUserList = $serializer->serialize($userList, 'json', ['groups' => 'getUsers']);

        return new JsonResponse($jsonUserList, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to get one user by his id.
     */
    #[Route('/api/users/{id}', name: 'oneUser', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Returns one user', content: new Model(type: User::class))]
    #[OA\Tag(name: 'User')]
    #[OA\Parameter(name: 'id', description: 'The id of the user', in: 'path', required: true, example: 1)]
    #[Security(name: 'Bearer')]
    public function getOneUser(User $user, SerializerInterface $serializer): JsonResponse
    {
        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getUsers']);

        return new JsonResponse($jsonUser, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to delete a user.
     */
    #[Route('/api/users/{id}', name: 'deleteUser', methods: ['DELETE'])]
    #[OA\Response(response: 204, description: 'Delete a user')]
    #[OA\Tag(name: 'User')]
    #[OA\Parameter(name: 'id', description: 'The id of the user', in: 'path', required: true, example: 1)]
    public function deleteUser(User $user, EntityManagerInterface $em): JsonResponse
    {
        $em->remove($user);
        $em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
