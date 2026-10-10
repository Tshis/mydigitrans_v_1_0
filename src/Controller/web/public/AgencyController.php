<?php

namespace App\Controller\web\public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AgencyController extends AbstractController
{

    #[Route('/public/agency/create', name: 'public_agency_add')]
    public function add(): Response
    {
        return $this->render('public/agency/add.html.twig', [
            'page' => 'agency',
        ]);
    } //add

    /**
     * ENRÔLEMENT : FORMULAIRE DOUBLE ROUTE DE CRÉATION D'AGENCE AVEC ACCROCHAGE SPONSOR [Profile, MNS]
     */
    #[Route('/register-agency', name: 'public_agency_register', methods: ['GET'])]
    #[Route('/register-agency/{referal_token}', name: 'public_agency_register_sponsored', methods: ['GET'])]
    public function showRegisterForm(?string $referal_token = null): Response
    {
        // Capture du jeton de parrainage s'il est présent dans l'URL (Ex: /register-agency/MDTDAN718) [MNS]
        $requestedSponsor = $referal_token;

        return $this->render('public/agency/register.html.twig', [
            'page' => 'agency',
            'requested_sponsor' => $requestedSponsor
        ]);
    }//showRegisterForm

    /**
     * PROCESSEUR DE SÉCURITÉ : TRAITEMENT ET INSCRIPTION COMPAGNIE
     */
    #[Route('/register-agency/process', name: 'public_agency_register_process', methods: ['POST'])]
    public function processRegister(Request $request): Response
    {
        // EN COULISSES DOCTRINE :
        // 1. Instanciation de la nouvelle "1. Agency" au statut d'attente
        // 2. Si sponsor_token est fourni, on pointe la table User pour lier l'agence à l'apporteur (parent_id) [Profile]
        // 3. Redirection vers la sélection immédiate du forfait (31. subscriptionPlan) [INDEX]

        $this->addFlash('success', 'Votre structure a été configurée avec succès. Choisissez votre formule SaaS.');
        return $this->redirectToRoute('public_login');
    } //processRegister
}
