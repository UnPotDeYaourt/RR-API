<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['getRessources', 'getCategories', 'getStats'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['getRessources', 'getCategories', 'createCategory', 'getStats'])]
    private ?string $label = null;

    #[ORM\Column(length: 255)]
    #[Groups(['getRessources', 'getCategories', 'createCategory', 'getStats'])]
    private ?string $name = null;

    #[ORM\Column]
    #[Groups(['getCategories'])]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Ressource::class)]
    private Collection $ressources;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Statistic::class)]
    private Collection $statistics;

    public static function create(string $label, string $name): self
    {
        $category = new self();
        $category->label = $label;
        $category->name = $name;

        return $category;
    }

    public function __construct()
    {
        $this->ressources = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->statistics = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->label;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId($id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, Ressource>
     */
    public function getRessources(): Collection
    {
        return $this->ressources;
    }

    public function addRessource(Ressource $ressource): self
    {
        if (!$this->ressources->contains($ressource)) {
            $this->ressources->add($ressource);
            $ressource->setCategory($this);
        }

        return $this;
    }

    public function removeRessource(Ressource $ressource): self
    {
        if ($this->ressources->removeElement($ressource)) {
            // set the owning side to null (unless already changed)
            if ($ressource->getCategory() === $this) {
                $ressource->setCategory(null);
            }
        }

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, Statistic>
     */
    public function getStatistics(): Collection
    {
        return $this->statistics;
    }

    public function addStatistic(Statistic $statistic): self
    {
        if (!$this->statistics->contains($statistic)) {
            $this->statistics->add($statistic);
            $statistic->setCategory($this);
        }

        return $this;
    }

    public function removeStatistic(Statistic $statistic): self
    {
        if ($this->statistics->removeElement($statistic)) {
            // set the owning side to null (unless already changed)
            if ($statistic->getCategory() === $this) {
                $statistic->setCategory(null);
            }
        }

        return $this;
    }
}
