<?php

namespace App\Controller\web\public;

use App\Dto\public\business\PartnerRegistrationData;
use App\Exception\PartnerRegistrationException;
use App\Form\PartnerRegistrationType;
use App\Service\PartnerRegistrationService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BusinessController extends AbstractController
{

    #[Route('/business', name: 'business_index')]
    public function index(): Response
    {
        return $this->render('public/business/index.html.twig', [
            'page' => 'business',
        ]);
    } //index


    /**
     * VUE PUBLIQUE : ACCÈS AU FORMULAIRE D'INSCRIPTION PARTENAIRE
     * Avec un lien d'affiliation valide, le parrain est préchargé et verrouillé.
     */
    #[Route('/business/devenir-partenaire', name: 'business_become_partner')]
    #[Route('/business/devenir-partenaire/{referal_token}', name: 'business_become_partner_sponsored')]
    public function become_partner(PartnerRegistrationService $registration, ?string $referal_token = null): Response
    {
        $data = new PartnerRegistrationData();
        $locked = false;

        if (null !== $referal_token) {
            if (null !== $registration->findSponsor($referal_token)) {
                $data->sponsorToken = trim($referal_token);
                $locked = true;
            } else {
                $this->addFlash('warning', 'Ce lien de parrainage n\'est pas valide. Vous pouvez tout de même vous inscrire.');
            }
        }

        $form = $this->createForm(PartnerRegistrationType::class, $data, [
            'action' => $this->generateUrl('public_partner_register_process'),
        ]);
        $form->get('sponsorLocked')->setData($locked ? '1' : '');

        return $this->render('public/business/become_partner.html.twig', [
            'page' => 'business',
            'form' => $form->createView(),
            'sponsor_locked' => $locked,
        ]);
    } //become_partner

    /**
     * TRAITEMENT DE L'INSCRIPTION ET PERSISTANCE DE L'APPORTEUR
     * Crée l'utilisateur partenaire, son jeton de parrainage, son rattachement au parrain (parent) et son rôle.
     */
    #[Route('/devenir-partenaire/process', name: 'public_partner_register_process', methods: ['POST'])]
    public function processRegister(Request $request, PartnerRegistrationService $registration): Response
    {
        $data = new PartnerRegistrationData();
        $form = $this->createForm(PartnerRegistrationType::class, $data, [
            'action' => $this->generateUrl('public_partner_register_process'),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $registration->register($data, new \DateTimeImmutable());

                $this->addFlash('success', 'Votre compte partenaire a été créé. Connectez-vous pour accéder à votre espace.');

                return $this->redirectToRoute('security_login_index');
            } catch (PartnerRegistrationException $e) {
                $target = null !== $e->getField() && $form->has($e->getField()) ? $form->get($e->getField()) : $form;
                $target->addError(new FormError($e->getMessage()));
                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('business_become_partner');
            } catch (UniqueConstraintViolationException) {
                // Deux inscriptions simultanées avec le même email : la contrainte d'unicité de la base a tranché.
                $form->get('email')->addError(new FormError('Cette adresse email est déjà utilisée.'));
                $error = $form->get('email')->addError(new FormError('Cette adresse email est déjà utilisée.'));
                $this->addFlash('error', $error);
                return $this->redirectToRoute('business_become_partner');
            }
        }

        return $this->render('public/business/become_partner.html.twig', [
            'page' => 'business',
            'form' => $form->createView(),
            'sponsor_locked' => '1' === (string) $form->get('sponsorLocked')->getData(),
        ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
    } //processRegister
}
