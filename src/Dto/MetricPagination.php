<?php

namespace App\Dto;

use Symfony\Component\Serializer\Attribute\Groups;

final class MetricPagination {
  #[Groups(['metric'])]
  private int $currentPage;

  #[Groups(['metric'])]
  private int $itemsPerPage;

  #[Groups(['metric'])]
  private int $totalItems;

  #[Groups(['metric'])]
  private int $totalPages;

  #[Groups(['metric'])]
  private string $order;

  public function __construct(int $currentPage, int $itemsPerPage, int $totalItems, int $totalPages, string $order) {
    $this->currentPage = $currentPage;
    $this->itemsPerPage = $itemsPerPage;
    $this->totalItems = $totalItems;
    $this->totalPages = $totalPages;
    $this->order = $order;
  }

  public function getCurrentPage(): int
  {
    return $this->currentPage;
  }

  public function setCurrentPage(int $currentPage): MetricPagination
  {
    $this->currentPage = $currentPage;
    return $this;
  }

  public function getItemsPerPage(): int
  {
    return $this->itemsPerPage;
  }

  public function setItemsPerPage(int $itemsPerPage): MetricPagination
  {
    $this->itemsPerPage = $itemsPerPage;
    return $this;
  }

  public function getTotalItems(): int
  {
    return $this->totalItems;
  }

  public function setTotalItems(int $totalItems): MetricPagination
  {
    $this->totalItems = $totalItems;
    return $this;
  }

  public function getTotalPages(): int
  {
    return $this->totalPages;
  }

  public function setTotalPages(int $totalPages): MetricPagination
  {
    $this->totalPages = $totalPages;
    return $this;
  }

  public function getOrder(): string
  {
    return $this->order;
  }

  public function setOrder(string $order): MetricPagination
  {
    $this->order = $order;
    return $this;
  }
}
