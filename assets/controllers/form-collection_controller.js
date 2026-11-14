import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static values = {
        addLabel: String,
        deleteLabel: String,
    };

    connect() {
        // Plus grand index existant + 1 : après une erreur de validation, Symfony réaffiche
        // les lignes avec leurs index d'origine (ex. seulement [1]), le nombre d'enfants ne suffit pas
        const indexes = [...this.element.children].map((child) =>
            parseInt(child.querySelector("[id]")?.id.match(/_(\d+)$/)?.[1] ?? -1),
        );
        this.index = Math.max(-1, ...indexes) + 1;
        const btn = document.createElement("button");
        btn.setAttribute("class", "btn theme-secondary");
        btn.innerText = this.addLabelValue || "Ajouter un élément";
        btn.setAttribute("type", "button");
        btn.addEventListener("click", this.addElement);
        [...this.element.children].forEach(this.addDeleteButton);
        this.element.append(btn);
    }

    /**
     * @param {MouseEvent} e
     */
    addElement = (e) => {
        e.preventDefault();
        const element = document
            .createRange()
            .createContextualFragment(
                this.element.dataset["prototype"].replaceAll(
                    "__name__",
                    this.index,
                ),
            ).firstElementChild;
        this.addDeleteButton(element);
        this.index++;
        e.currentTarget.insertAdjacentElement("beforebegin", element);
    };

    /**
     * @param {HTMLElement} item
     */
    addDeleteButton = (item) => {
        const btn = document.createElement("button");
        btn.setAttribute("class", "btn theme-secondary");
        btn.innerText = this.deleteLabelValue || "Supprimer";
        btn.setAttribute("type", "button");
        item.append(btn);
        btn.addEventListener("click", (e) => {
            e.preventDefault();
            item.remove();
        });
    };
}
