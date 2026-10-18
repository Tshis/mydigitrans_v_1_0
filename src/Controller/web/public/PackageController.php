<?php

namespace App\Controller\web\public;

use App\Dto\public\package\PackageSearchData;
use App\Form\PackageSearchType;
use App\Service\PackageSearcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PackageController extends AbstractController
{
    #[Route('/colis', name: 'public_package_index', methods: ['GET'])]
    public function index(): Response
    {
        $form = $this->createForm(PackageSearchType::class);

        return $this->render('public/package/index.html.twig', [
            'page' => 'colis',
            'searchForm' => $form->createView(),
        ]);
    } // index

    /**
     * VÉRIFICATION DES SOUTES ET DEPARTS DISPONIBLES POUR LES COLIS
     */
    #[Route('/colis/resultats', name: 'public_package_results', methods: ['GET'])]
    public function results(Request $request, PackageSearcher $searcher): Response
    {
        $form = $this->createForm(PackageSearchType::class);
        $form->handleRequest($request);

        // Accès direct à l'URL sans critères : retour au formulaire.
        if (!$form->isSubmitted()) {
            return $this->redirectToRoute('public_package_index');
        }

        // Critères invalides : on réaffiche le formulaire avec les erreurs.
        if (!$form->isValid()) {
            return $this->render('public/package/index.html.twig', [
                'page' => 'colis',
                'searchForm' => $form->createView(),
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        /** @var PackageSearchData $criteria */
        $criteria = $form->getData();

        return $this->render('public/package/resultats.html.twig', [
            'page' => 'colis',
            'search_query' => $criteria,
            'available_trips' => $searcher->search($criteria),
        ]);
    } // results


    /**
     * ACCROCHAGE DE LA SELECTION DE SOUTE COLIS
     */
    #[Route('/public/package/reserver', name: 'public_package_book', methods: ['POST'])]
    public function bookPackage(Request $request): Response
    {
        //Imprimer le bon de reservation et retournez à l'index colis.

        $this->addFlash('success', 'Départ sélectionné. Renseignez à présent l\'identité de l\'expéditeur et du destinataire.');
        return $this->redirectToRoute('public_package_results');
    } //bookPackage

}
