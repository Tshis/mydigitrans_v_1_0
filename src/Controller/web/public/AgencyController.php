<?php

namespace App\Controller\web\public;


use App\Dto\public\agency\AgencyRegistrationData;
use App\Exception\AgencyRegistrationException;
use App\Form\AgencyRegistrationType;
use App\Service\AgencyRegistrationService;
use App\Service\PartnerRegistrationService;
use App\Service\SubscriptionPlanCatalog;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Création d'une agence (inscription publique) puis choix du plan d'abonnement.
 * À fusionner avec ton contrôleur d'agence existant : adapte les chemins à ceux de tes routes.
 */
final class AgencyController extends AbstractController
{
    /** Agence en cours d'inscription : conservée en session le temps de choisir un plan. */
    private const ONBOARDING_SESSION_KEY = 'onboarding_agency_id';

    /**
     * ÉTAPE 1 : AFFICHAGE ET TRAITEMENT DE L'INSCRIPTION
     * Gère à la fois l'affichage initial (GET) et la soumission du formulaire (POST).
     */
    #[Route('/agence/inscription', name: 'public_agency_register', methods: ['GET', 'POST'])]
    #[Route('/agence/inscription/{referal_token}', name: 'public_agency_register_sponsored', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        AgencyRegistrationService $registration,
        PartnerRegistrationService $partners,
        ?string $referal_token = null
    ): Response {
        $data = new AgencyRegistrationData();
        $locked = false;

        // Préchargement du jeton de parrainage uniquement en cas d'affichage initial (GET)
        if (!$request->isMethod('POST') && null !== $referal_token) {
            if (null !== $partners->findSponsor($referal_token)) {
                $data->sponsorToken = trim($referal_token);
                $locked = true;
            } else {
                $this->addFlash('warning', 'Ce lien d\'invitation n\'est pas valide. Vous pouvez tout de même créer votre agence.');
            }
        }

        // Création du formulaire (sans forcer l'option 'action' pour qu'il reste sur l'URL courante)
        $form = $this->createForm(AgencyRegistrationType::class, $data);

        if (!$request->isMethod('POST')) {
            $form->get('sponsorLocked')->setData($locked ? '1' : '');
        }

        // Traitement de la requête
        $form->handleRequest($request);

        // Traitement après soumission et validation
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $result = $registration->register($data, new \DateTimeImmutable());

                $request->getSession()->set(self::ONBOARDING_SESSION_KEY, $result->agency->getId());
                $this->addFlash('success', sprintf(
                    'L\'agence « %s » est créée avec son siège de %s. Choisissez maintenant votre plan d\'abonnement.',
                    $result->agency->getName(),
                    $result->headquarters->getCity()
                ));

                return $this->redirectToRoute('public_agency_choose_plan');
            } catch (AgencyRegistrationException $e) {
                foreach ($e->getErrors() as $field => $message) {
                    $target = $form->has($field) ? $form->get($field) : $form;
                    $target->addError(new FormError($message));
                }
            } catch (UniqueConstraintViolationException) {
                $form->addError(new FormError('Une agence ou un compte avec ces informations existe déjà.'));
            }
        }

        $status = $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK;

        return $this->render('public/agency/register.html.twig', [
            'page' => 'agency',
            'form' => $form->createView(),
            'sponsor_locked' => '1' === (string) $form->get('sponsorLocked')->getData() || $locked,
        ], new Response(status: $status));
    }//register




    /**
     * ÉTAPE 2 : CHOIX DU PLAN D'ABONNEMENT
     * Réservée à l'agence qui vient d'être créée dans cette session.
     */
    #[Route('/agence/choisir-un-plan', name: 'public_agency_choose_plan', methods: ['GET'])]
    public function choose_plan(Request $request, SubscriptionPlanCatalog $catalog): Response
    {
        if (!$request->getSession()->has(self::ONBOARDING_SESSION_KEY)) {
            $this->addFlash('warning', 'Créez d\'abord votre agence pour choisir un plan d\'abonnement.');

            return $this->redirectToRoute('public_agency_register');
        }

        return $this->render('public/agency/choose_plan.html.twig', [
            'page' => 'agency',
            'platform_plans' => $catalog->activePlans(),
        ]);
    } //choose_plan
}
