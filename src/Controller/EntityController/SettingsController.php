<?php

namespace App\Controller\EntityController;

use App\Entity\Settings;
use App\Entity\User;
use App\Repository\LanguageRepository;
use App\Repository\SettingsRepository;
use App\Repository\ThemeRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use phpDocumentor\Reflection\DocBlock\Tags\Property;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

class SettingsController extends AbstractController
{
    /**
     * This function allows us to get all settings of a user.
     */
    #[Route('/api/settings/user', name: 'userSettings', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Returns user settings')]
    #[OA\Tag(name: 'Settings')]
    public function getUserSettings(SettingsRepository $settingsRepository, SerializerInterface $serializer): JsonResponse
    {
        // Get user settings
        /** @var User $user */
        $user = $this->getUser();
        $settings = $settingsRepository->findOneBy(['user' => $user]);
        $jsonSettings = $serializer->serialize($settings, 'json', ['groups' => 'getSettings']);

        return new JsonResponse($jsonSettings, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to get all themes.
     */
    #[OA\Tag(name: 'Settings')]
    #[Route('/api/settings/themes', name: 'getAllThemes', methods: ['GET'])]
    public function getAllThemes(ThemeRepository $themeRepository, SerializerInterface $serializer): JsonResponse
    {
        $themes = $themeRepository->findAll();
        $jsonThemes = $serializer->serialize($themes, 'json', ['groups' => 'getThemes']);

        return new JsonResponse($jsonThemes, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to get all languages.
     */
    #[OA\Tag(name: 'Settings')]
    #[Route('/api/settings/languages', name: 'getAllLanguages', methods: ['GET'])]
    public function getAllLanguages(LanguageRepository $languageRepository, SerializerInterface $serializer): JsonResponse
    {
        $languages = $languageRepository->findAll();
        $jsonLanguages = $serializer->serialize($languages, 'json', ['groups' => 'getLanguages']);

        return new JsonResponse($jsonLanguages, Response::HTTP_OK, [], true);
    }

    /**
     * This function allows us to update settings.
     */
    #[Route('/api/settings/update', name: 'updateSettings', methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'Returns updated settings')]
    #[OA\Tag(name: 'Settings')]
    #[OA\RequestBody(required: true, attachables: [
        new OA\MediaType(
            mediaType: 'application/json',
            schema: new OA\Schema(
                type: 'object',
                properties: [
                    new OA\Property(property: 'theme', type: 'int', example: '1'),
                    new OA\Property(property: 'isDark', type: 'boolean', example: 'true'),
                    new OA\Property(property: 'allowNotifications', type: 'boolean', example: 'true'),
                    new OA\Property(property: 'useDeviceMode', type: 'boolean', example: 'true'),
                    new OA\Property(property: 'language', type: 'int', example: '1'),
                ]
            )
        ),
    ])]
    public function updateSettings(EntityManagerInterface $entityManager,
                                   Request $request,
                                   SettingsRepository $settingsRepository,
                                   ThemeRepository $themeRepository,
                                   LanguageRepository $languageRepository,
                                   SerializerInterface $serializer): JsonResponse
    {
        $content = $request->toArray();

        $user_settings = $settingsRepository->findOneBy(['user' => $this->getUser()]);

        if (!$user_settings) {
            return new JsonResponse('Settings not found', Response::HTTP_NOT_FOUND);
        }

        $theme = $themeRepository->findOneBy(["id" => $content['theme']]);
        $language = $languageRepository->findOneBy(['id' => $content['language']]);

        $user_settings->setTheme($theme);
        $user_settings->setIsDark($content['isDark']);
        $user_settings->setAllowNotifications($content['allowNotifications']);
        $user_settings->setUseDeviceMode($content['useDeviceMode']);
        $user_settings->setLanguage($language);

        $entityManager->persist($user_settings);
        $entityManager->flush();

        return new JsonResponse('Settings updated', Response::HTTP_OK);
    }


    /**
     * @param EntityManagerInterface $entityManager
     * @param Request $request
     * @param SettingsRepository $settingsRepository
     * @return JsonResponse
     */
    #[Route('/api/settings/update-device-mode', name: 'settings', methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'Returns settings')]
    #[OA\Tag(name: 'Settings')]
    public function updateDeviceMode(EntityManagerInterface $entityManager,
                                     Request $request,
                                     SettingsRepository $settingsRepository): JsonResponse
    {
        $content = $request->toArray();

        $user_settings = $settingsRepository->findOneBy(['user' => $this->getUser()]);

        if (!$user_settings) {
            return new JsonResponse('Settings not found', Response::HTTP_NOT_FOUND);
        }

        $user_settings->setUseDeviceMode($content['useDeviceMode']);

        $entityManager->persist($user_settings);
        $entityManager->flush();

        return new JsonResponse('Settings updated', Response::HTTP_OK);
    }

    #[Route('/api/settings/update-theme', name: 'settings', methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'Returns settings')]
    #[OA\Tag(name: 'Settings')]
    public function updateTheme(EntityManagerInterface $entityManager,
                                Request $request,
                                SettingsRepository $settingsRepository,
                                ThemeRepository $themeRepository): JsonResponse
    {
        $content = $request->toArray();

        $user_settings = $settingsRepository->findOneBy(['user' => $this->getUser()]);

        if (!$user_settings) {
            return new JsonResponse('Settings not found', Response::HTTP_NOT_FOUND);
        }

        $theme = $themeRepository->findOneBy(["id" => $content['theme']]);

        $user_settings->setTheme($theme);

        $entityManager->persist($user_settings);
        $entityManager->flush();

        return new JsonResponse('Settings updated', Response::HTTP_OK);
    }

    #[Route('/api/settings/update-language', name: 'settings', methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'Returns settings')]
    #[OA\Tag(name: 'Settings')]
    public function updateLanguage(EntityManagerInterface $entityManager,
                                   Request $request,
                                   SettingsRepository $settingsRepository,
                                   LanguageRepository $languageRepository): JsonResponse
    {
        $content = $request->toArray();

        $user_settings = $settingsRepository->findOneBy(['user' => $this->getUser()]);

        if (!$user_settings) {
            return new JsonResponse('Settings not found', Response::HTTP_NOT_FOUND);
        }

        $language = $languageRepository->findOneBy(['id' => $content['language']]);

        $user_settings->setLanguage($language);

        $entityManager->persist($user_settings);
        $entityManager->flush();

        return new JsonResponse('Settings updated', Response::HTTP_OK);
    }

    #[Route('/api/settings/update-is-dark', name: 'settings', methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'Returns settings')]
    #[OA\Tag(name: 'Settings')]
    public function updateIsDark(EntityManagerInterface $entityManager,
                                 Request $request,
                                 SettingsRepository $settingsRepository): JsonResponse
    {
        $content = $request->toArray();

        $user_settings = $settingsRepository->findOneBy(['user' => $this->getUser()]);

        if (!$user_settings) {
            return new JsonResponse('Settings not found', Response::HTTP_NOT_FOUND);
        }

        $user_settings->setIsDark($content['isDark']);

        $entityManager->persist($user_settings);
        $entityManager->flush();

        return new JsonResponse('Settings updated', Response::HTTP_OK);
    }

    #[Route('/api/settings/update-allow-notifications', name: 'settings', methods: ['PUT'])]
    #[OA\Response(response: 200, description: 'Returns settings')]
    #[OA\Tag(name: 'Settings')]
    public function updateAllowNotifications(EntityManagerInterface $entityManager,
                                             Request $request,
                                             SettingsRepository $settingsRepository): JsonResponse
    {
        $content = $request->toArray();

        $user_settings = $settingsRepository->findOneBy(['user' => $this->getUser()]);

        if (!$user_settings) {
            return new JsonResponse('Settings not found', Response::HTTP_NOT_FOUND);
        }

        $user_settings->setAllowNotifications($content['allowNotifications']);

        $entityManager->persist($user_settings);
        $entityManager->flush();

        return new JsonResponse('Settings updated', Response::HTTP_OK);
    }

    /**
     * This function allows us to get settings by id.
     */
    #[Route('/api/settings/{id}', name: 'settings', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Returns settings')]
    #[OA\Tag(name: 'Settings')]
    #[OA\Parameter(name: 'id', description: 'The id of the settings', in: 'path', required: true, example: 1)]
    public function getOneSettings(Settings $settings, SerializerInterface $serializer): JsonResponse
    {
        $jsonSettings = $serializer->serialize($settings, 'json', ['groups' => 'getSettings']);

        return new JsonResponse($jsonSettings, Response::HTTP_OK, [], true);
    }



}
