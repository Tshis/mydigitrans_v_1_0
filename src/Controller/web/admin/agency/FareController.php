<?php

declare(strict_types=1);

namespace App\Controller\web\admin\agency;

use App\Controller\web\admin\AbstractAdminController;
use App\Entity\Route as RouteEntity;
use App\Enum\FareType;
use App\Enum\Scope;
use App\Form\agency\FareMatrixType;
use App\Service\FareService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FareController extends AbstractAdminController
{
    public function __construct(
        private readonly FareService $fareService,
    ) {}

    protected function scope(): Scope
    {
        return Scope::Agency;
    }

    #[Route('/admin/agency/fare/add', name: 'admin_agency_fare_add', methods: ['GET', 'POST'])]
    #[Route('/admin/agency/line/{slug}/fare/add', name: 'admin_agency_fare_line_add', methods: ['GET', 'POST'])]
    public function fareLineAdd(Request $request, ?string $slug = null): Response
    {
        $this->denyAccessUnlessGranted('fare.write');
        $actor  = $this->actor();
        $agency = $actor->getAgency();

        $allRoutes = $this->fareService->getAgencyRoutes($agency);
        $route = $this->fareService->findAgencyRouteBySlug($agency, $slug);

        if (!$route instanceof RouteEntity) {
            $this->addFlash('error', 'Veuillez d\'abord créer une ligne de transport avant de configurer des tarifs.');
            return $this->redirectToRoute('admin_agency_line_add');
        }

        // Permet de charger les tarifs existants pour Standard ou VIP via ?fareType=...
        $requestedType = (string) $request->query->get('fareType', FareType::Standard->value);
        $fareType = FareType::tryFrom($requestedType) ?? FareType::Standard;

        $dto = $this->fareService->buildMatrixDto($route, $fareType);
        $form = $this->createForm(FareMatrixType::class, $dto, [
            'currency_choices' => $this->fareService->getAgencyCurrencyChoices($agency),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $count = $this->fareService->saveMatrix($actor, $route, $dto);
                $this->addFlash('success', sprintf(
                    'Grille tarifaire (%s) enregistrée avec succès : %d tarif(s) actif(s) sur la ligne %s.',
                    ucfirst($dto->fareType->value),
                    $count,
                    $route->getName()
                ));

                return $this->redirectToRoute('admin_agency_line_show', ['slug' => $route->getSlug()]);
            } catch (\DomainException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('admin/agency/fare/add.html.twig', [
            'page'       => 'fare',
            'form'       => $form,
            'route'      => $this->fareService->getRouteSummary($route),
            'all_routes' => $allRoutes,
        ]);
    }
}
