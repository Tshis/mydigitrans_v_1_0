<?php

declare(strict_types=1);

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
            'page'       => 'colis',
            'searchForm' => $form->createView(),
        ]);
    }

    /**
     * VÉRIFICATION DES SOUTES ET DÉPARTS DISPONIBLES POUR LES COLIS
     */
    #[Route('/colis/resultats', name: 'public_package_results', methods: ['GET'])]
    public function results(Request $request, PackageSearcher $searcher): Response
    {
        $form = $this->createForm(PackageSearchType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            return $this->redirectToRoute('public_package_index');
        }

        if (!$form->isValid()) {
            return $this->render('public/package/index.html.twig', [
                'page'       => 'colis',
                'searchForm' => $form->createView(),
            ], new Response(status: Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        /** @var PackageSearchData $criteria */
        $criteria = $form->getData();

        return $this->render('public/package/resultats.html.twig', [
            'page'            => 'colis',
            'search_query'    => $criteria,
            'available_trips' => $searcher->search($criteria),
        ]);
    }

    /**
     * ACCROCHAGE DE LA SÉLECTION DE SOUTE COLIS
     */
    #[Route('/public/package/reserver', name: 'public_package_book', methods: ['POST'])]
    public function bookPackage(Request $request): Response
    {
        $tripCode = strtoupper(trim((string) $request->request->get('trip_code', '')));
        $branchName = trim((string) $request->request->get('departure_branch', 'la gare de départ'));

        $this->addFlash(
            'success',
            sprintf(
                'Départ %s sélectionné. Présentez votre colis au guichet fret de %s au moins 1 heure avant le départ.',
                '' !== $tripCode ? '[' . $tripCode . ']' : '',
                $branchName
            )
        );

        return $this->redirectToRoute('public_package_index');
    }
}
