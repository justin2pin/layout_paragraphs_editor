(($, Drupal, drupalSettings, dragula) => {
  class LPEditor {
    constructor(settings) {
      this.settings = settings;
      this.$element = $(settings.selector);
      this.componentMenu = settings.componentMenu;
      this.sectionMenu = settings.sectionMenu;
      this.controls = settings.controls;
      this.toggleButton = settings.toggleButton;
      this.emptyContainer = settings.emptyContainer;
      this.$actions = this.$element.find(".lpe-controls");
      this.trashBin = [];
      this._intervalId = 0;
      this._interval = 200;
      this._statusIntervalId = 0;
      this._statusInterval = 3000;
      this.$banner = $("body").append(
        this.$element.find(".lpe-banner__wrapper")
      );

      if (this.$element.find(".lpe-component").length === 0) {
        this.isEmpty();
      }

      this.attachEventListeners();
      this.enableDragAndDrop();
      this.saved();
    }

    attachEventListeners() {
      this.$element.on(
        "focus.lp-editor",
        ".lpe-component",
        this.onFocusComponent.bind(this)
      );
      this.$element.on(
        "focus.lp-editor",
        ".lpe-region",
        this.onFocusRegion.bind(this)
      );

      // Handle click for main save button.
      $(".lpe-save", this.$element).click(e => {
        this.save();
        return false;
      });

      // Handle click for main cancel button.
      $(".lpe-cancel", this.$element).click(e => {
        this.cancel();
        return false;
      });

      this.$element.on("mousemove.lp-editor", this.onMouseMove.bind(this));

      this.$element.on(
        "click.lp-editor",
        ".lpe-edit",
        this.onClickEdit.bind(this)
      );
      this.$element.on(
        "click.lp-editor",
        ".lpe-delete",
        this.onClickDelete.bind(this)
      );
      this.$element.on(
        "click.lp-editor",
        ".lpe-toggle",
        this.onClickToggle.bind(this)
      );
      this.$element.on(
        "click.lp-editor",
        ".lpe-down",
        this.onClickDown.bind(this)
      );
      this.$element.on("click.lp-editor", ".lpe-up", this.onClickUp.bind(this));
      this.$element.on(
        "click.lp-editor",
        ".lp-editor-component-menu__action",
        this.onClickComponentAction.bind(this)
      );
      this.$element.on(
        "click.lp-editor",
        ".lpe-section-menu-button",
        this.onClickSectionAction.bind(this)
      );
      this.onKeyPress = this.onKeyPress.bind(this);
      document.addEventListener("keydown", this.onKeyPress);
    }

    onMouseMove(e) {
      if (!this.$componentMenu) {
        this.startInterval();
      }
    }

    /**
     * On focus event handler for components.
     * @param {Event} e The event.
     */
    onFocusComponent(e) {
      this.$activeItem = $(e.currentTarget);
      e.stopPropagation();
    }

    /**
     * On focus event handler for regions.
     * @param {Event} e The event.
     */
    onFocusRegion(e) {
      if ($(".lpe-component", e.currentTarget)) {
        this.$activeItem = $(e.currentTarget);
        e.stopPropagation();
      }
    }

    /**
     * Interval handler.
     */
    onInterval() {
      const $hoveredItem = this.$element
        .find(".lpe-component:hover, .lpe-region:hover")
        .last();
      if ($hoveredItem.length > 0) {
        this.$activeItem = $hoveredItem;
      } else {
        this.$activeItem = false;
      }
      this.stopInterval();
    }

    startInterval() {
      if (!this._intervalId) {
        this._intervalId = setInterval(
          this.onInterval.bind(this),
          this._interval
        );
      }
    }

    stopInterval() {
      clearInterval(this._intervalId);
      this._intervalId = 0;
    }

    /**
     * Edit component button click event handler.
     * @param {Event} e The triggering event.
     */
    onClickEdit(e) {
      e.currentTarget.classList.add("loading");
      this.editForm();
    }

    /**
     * Delete component button click event handler.
     * @param {Event} e The triggering event.
     */
    onClickDelete(e) {
      this.delete($(e.currentTarget).closest(".lpe-component"));
    }

    /**
     * Toggle create menu button click event handler.
     * @param {Event} e The triggering event.
     */
    onClickToggle(e) {
      this.toggleComponentMenu($(e.currentTarget));
    }

    /**
     * Move component up button click event handler.
     * @param {Event} e The triggering event.
     */
    onClickUp(e) {
      this.move(-1);
    }

    /**
     * Move component down button click event handler.
     * @param {Event} e The triggering event.
     */
    onClickDown(e) {
      this.move(1);
    }

    onClickComponentAction(e) {
      const placement = this.$activeToggle.attr("data-placement");
      const region = this.$activeToggle.attr("data-region");
      const uuid = this.$activeToggle.attr("data-container-uuid");
      const type = $(e.currentTarget).attr("data-type");
      switch (placement) {
        case "insert":
          this.insertComponentIntoRegion(uuid, region, type);
          break;
        // If placement is "before" or "after".
        default:
          this.insertSiblingComponent(uuid, type, placement);
          break;
      }
      return false;
    }

    onClickSectionAction(e) {
      const $button = $(e.currentTarget);
      const $sectionMenu = $button.closest(".js-lpe-section-menu");
      const placement = $sectionMenu.attr("data-placement");
      const uuid = $sectionMenu.attr("data-container-uuid");
      const type = $button.attr("data-type");
      if (uuid) {
        this.insertSiblingComponent(uuid, type, placement);
      } else {
        this.insertComponent(type);
      }

    }

    /**
     * Key press event handler.
     * @param {Event} e The triggering event.
     */
    onKeyPress(e) {
      if (e.code === "Escape") {
        if (this.$componentMenu) {
          this.closeComponentMenu();
        } else {
          this.cancel();
        }
      }
    }

    detachEventListeners() {
      this.$element.off(".lp-editor");
      this.$element.off(".lp-editor");
      clearInterval(this._intervalId);
      document.removeEventListener("keydown", this.onKeyPress);
    }

    getState() {
      return $(".lpe-component", this.$element)
        .get()
        .map(item => {
          const $item = $(item);
          return {
            uuid: $item.attr("data-uuid"),
            parentUuid:
              $item
                .parents(".lpe-component")
                .first()
                .attr("data-uuid") || null,
            region:
              $item
                .parents(".lpe-region")
                .first()
                .attr("data-region") || null
          };
        });
    }

    set $activeItem($item) {
      if (this.$componentMenu) {
        return;
      }
      // If item is already is already active, do nothing.
      if (
        $item.length &&
        this.$activeItem &&
        this.$activeItem[0] === $item[0]
      ) {
        return;
      }
      // If item is false and activeItem is also false, do nothing.
      if ($item === false && this._$activeItem === false) {
        return;
      }

      // Remove the current toggle or controls.
      if (this.$activeItem) {
        this.$activeItem.removeClass("js-lpe-active-item");
        this.removeControls();
      }
      // If $element exists and is not false, add the controls.
      if ($item) {
        if ($item.hasClass("lpe-component")) {
          this.insertControls($item);
          $item.addClass("js-lpe-active-item");
        } else if (
          $item.hasClass("lpe-region") &&
          $item.find(".lpe-component").length === 0
        ) {
          this.insertToggle($item, "insert", "append");
          $item.addClass("js-lpe-active-item");
        }
      }
      this._$activeItem = $item;
    }

    get $activeItem() {
      return this._$activeItem;
    }

    removeToggle() {
      //this.$element.find(".lpe-toggle").remove();
      if (this.$componentMenu) {
        this.$componentMenu.remove();
      }
    }

    /**
     * Insertss a toggle button into a container.
     * @param {jQuery} $container The container.
     * @param {string} placement Placement - inside|after|before.
     * @param {string} method jQuery method - prepend|append
     */
    insertToggle($container, placement, method = "prepend") {
      const $toggleButton = $(`<div class="js-lpe-toggle lpe-toggle__wrapper"></div>`)
        .append(
          $(this.toggleButton).attr({
            "data-placement": placement,
            "data-region": $container.attr("data-region"),
            "data-container-uuid": $container
              .closest("[data-uuid]")
              .attr("data-uuid")
          })
        )
        .css({ position: "absolute", zIndex: 1000 })
        .hide()
        [`${method}To`]($container)
        .fadeIn(100);

      const offset = $container.offset();
      const toggleHeight = $toggleButton.height();
      const toggleWidth = $toggleButton.outerWidth();
      const height = $container.outerHeight();
      const width = $container.outerWidth();
      const left = Math.floor(offset.left + width / 2 - toggleWidth / 2);
      console.log(toggleHeight);
      console.log(height);
      let top = "";
      switch (placement) {
        case "insert":
          top = Math.floor(offset.top + height / 2 - toggleHeight / 2);
          break;
        case "after":
          top = Math.floor(offset.top + height - toggleHeight / 2) - 1;
          break;
        case "before":
          top = Math.floor(offset.top - toggleHeight / 2) - 1;
          break;
        default:
          top = null;
      }

      $toggleButton.offset({ left, top });
    }

    insertSectionMenu($container, placement, method) {
      const $sectionMenu = $(
        `<div class="js-lpe-section-menu lpe-section-menu__wrapper">${this.sectionMenu}</div>`
      )
        .attr({
          "data-placement": placement,
          "data-container-uuid": $container
            .closest("[data-uuid]")
            .attr("data-uuid")
        })
        .css({ position: "absolute" })
        [`${method}To`]($container);
      const offset = $container.offset();
      const height = $container.outerHeight();
      const width = $container.width();
      const sectionMenuHeight = $sectionMenu.height();
      const sectionMenuWidth = $sectionMenu.width();
      const left = Math.floor(offset.left + width / 2 - sectionMenuWidth / 2);
      const top =
        placement === "before"
          ? Math.floor(offset.top - sectionMenuHeight / 2)
          : Math.floor(offset.top + height - sectionMenuHeight / 2);
      $sectionMenu.offset({ top, left });
    }

    removeControls() {
      if (this.$activeItem) {
        this.$activeItem.find(".js-lpe-controls").remove();
      }
      this.$element.find(".js-lpe-toggle").remove();
      this.$element.find(".js-lpe-section-menu").remove();
      this.$element.find(".lp-editor-controls-menu").remove();
      this.$componentMenu = false;
      this.$activeToggle = false;
    }

    insertControls($element) {
      $element.addClass("js-lpe-active-item");
      const $controls = $(`<div class="js-lpe-controls lpe-controls__wrapper">${this.controls}</div>`)
        .css({ position: "absolute" })
        .hide();
      const offset = $element.offset();
      this.$element.find(".js-lpe-controls").remove();
      $element.prepend($controls.fadeIn(200));
      if (
        $element.parents(".lpe-layout").length === 0 &&
        this.settings.requireSections
      ) {
        this.insertSectionMenu($element, "before", "prepend");
        this.insertSectionMenu($element, "after", "append");
      } else {
        this.insertToggle($element, "before", "prepend");
        this.insertToggle($element, "after", "append");
      }
      $controls.offset(offset);
    }

    /**
     * Toggles the create content component menu.
     * @param {jQuery} $toggleButton The button that triggered the toggle.
     */
    toggleComponentMenu($toggleButton) {
      if (this.$componentMenu) {
        this.closeComponentMenu($toggleButton);
      } else {
        this.openComponentMenu($toggleButton);
      }
    }

    /**
     * Opens the component menu.
     * @param {jQuery} $toggleButton The toggle button that was pressed.
     */
    openComponentMenu($toggleButton) {
      this.$activeToggle = $toggleButton;
      this.$activeToggle.addClass("active");
      this.$element
        .find(".lpe-toggle, .js-lpe-controls")
        .not(".active")
        .hide();
      this.$componentMenu = $(
        `<div class="js-lpe-component-menu-wrapper">${this.componentMenu}</div>`
      );
      if (this.settings.nestedSections === false) {
        if (this.$activeToggle.parents(".lpe-layout").length > 0) {
          this.$componentMenu
            .find(".lp-editor-component-menu__group--layout")
            .hide();
        }
      }
      this.$activeToggle.after(this.$componentMenu);
      this.positionComponentMenu();
      this.stopInterval();
    }

    /**
     * Position the component menu correctly.
     * @param {bool} keepOrientation If true, the menu will stay above/below no matter what.
     */
    positionComponentMenu(keepOrientation) {
      // Move the menu to correct spot.
      const btnOffset = this.$activeToggle.offset();
      const menuOffset = this.$componentMenu.offset();
      const viewportTop = $(window).scrollTop();
      const viewportBottom = viewportTop + $(window).height();
      const menuWidth = this.$componentMenu.outerWidth();
      const btnWidth = this.$activeToggle.outerWidth();
      const btnHeight = this.$activeToggle.height();
      const menuHeight = this.$componentMenu.outerHeight();

      // Accounts for rotation by calculating distance between points on 45 degree rotated square.
      const left = Math.floor(btnOffset.left + btnWidth / 2 - menuWidth / 2);

      // Default to positioning the menu beneath the button.
      let orientation = "beneath";
      let top = Math.floor(btnOffset.top + btnHeight * 1.5);

      // The menu is above the button, keep it that way.
      if (keepOrientation === true && menuOffset.top < btnOffset.top) {
        orientation = "above";
      }
      // The menu would go out of the viewport, so keep at top.
      if (top + menuHeight > viewportBottom) {
        orientation = "above";
      }
      this.$componentMenu
        .removeClass("above")
        .removeClass("beneath")
        .addClass(orientation);

      if (orientation === "above") {
        top = Math.floor(
          btnOffset.top -
            (menuHeight -
              parseInt(this.$componentMenu.css("padding-bottom"), 10))
        );
      }

      this.$componentMenu.removeClass("hidden").addClass("fade-in");
      this.$componentMenu.offset({ top, left });
    }

    closeComponentMenu() {
      this.$componentMenu.remove();
      this.$element.find(".lpe-toggle.active").removeClass("active");
      this.$element.find(".lpe-toggle, .js-lpe-controls").show();
      this.$componentMenu = false;
      this.$activeToggle = false;
      this.startInterval();
    }

    /**
     * Loads an edit form.
     */
    editForm() {
      const uuid = this.$activeItem.attr("data-uuid");
      const endpoint = `${this.settings.baseUrl}/edit/${uuid}`;
      Drupal.ajax({
        url: endpoint,
        submit: {
          layoutParagraphsState: JSON.stringify(this.getState())
        }
      })
        .execute()
        .done(() => {
          this.removeControls();
          this.removeToggle();
        });
    }

    statusMessage($target, message, actions, method = "append") {
      const parsedActions = actions.reduce(
        (accumulator, currentValue, index) => {
          const tag = `<a href="#" class="lpe-status__action lpe-status__action_${index}">${currentValue.label}</a>`;
          this.$element.on(
            "click.lp-editor",
            `.lpe-status__action_${index}`,
            e => {
              currentValue.action.call(this);
              this.clearStatusMessage();
              return false;
            }
          );
          accumulator += tag;
          return accumulator;
        },
        ""
      );
      const $status = $(`
        <div class="lpe-status">
          <div class="lpe-status-container">
            <div class="lpe-status-wrapper">
              <div class="lpe-status__message">${message}</div>
              <div class="lpe-status__actions">
                ${parsedActions}
              </div>
            </div>
          </div>
        </div>
      `);
      $target[method]($status);
      this._statusIntervalId = setInterval(() => {
        if (this.$element.find(".lpe-status:hover").length === 0) {
          this.clearStatusMessage();
        }
      }, this._statusInterval);
      return this;
    }

    clearStatusMessage() {
      clearInterval(this._statusIntervalId);
      this.$element.find(".lpe-status").fadeOut(100, () => {
        this.$element.find(".lpe-status").remove();
      });
    }

    /**
     * Delete a component.
     * @param {jQuery} $item The item to delete.
     */
    delete($item) {
      this.trashBin.push($item);
      const uuid = $item.attr("data-uuid");
      const $placeHolder = $(
        `<span class="hidden js-lpe-placeholder" data-uuid="${uuid}"></span>`
      );
      $item.fadeOut(200, () => {
        $item.replaceWith($placeHolder);
        this.statusMessage(
          $placeHolder,
          Drupal.t("Component deleted."),
          [
            {
              label: Drupal.t("Undo"),
              action: this.restore
            }
          ],
          "before"
        );
      });
      this.edited();
    }

    /**
     * Restores the last deleted item.
     */
    restore() {
      const $item = this.trashBin.pop();
      const uuid = $item.attr("data-uuid");
      $(`[data-uuid="${uuid}"]`, this.$elment).replaceWith($item);
      $item.fadeIn(100);
    }

    /**
     * Makes the Ajax reqeust to save the layout.
     */
    save() {
      $(".lpe-save", this.$element).text(Drupal.t("Saving..."));
      $(".lpe-cancel", this.$element).hide();
      const deleteUuids = this.trashBin.reduce((uuids, $current) => {
        uuids.push($current.attr("data-uuid"));
        $current.find(".lpe-component").each((i, item) => {
          uuids.push($(item).attr("data-uuid"));
        });
        return uuids;
      }, []);

      Drupal.ajax({
        url: `${this.settings.baseUrl}/save`,
        submit: {
          layoutParagraphsState: JSON.stringify(this.getState()),
          deleteComponents: JSON.stringify(deleteUuids)
        }
      }).execute();
    }

    /**
     * Makes the Ajax request to cancel and exit the editor.
     */
    cancel() {
      const instance = this;
      this.$element.find(".lpe-cancel").text(Drupal.t("Closing..."));
      Drupal.ajax({
        url: `${this.settings.baseUrl}/cancel`
      })
        .execute()
        .done(e => {
          instance.detachEventListeners();
        });
    }

    insertSiblingComponent(siblingUuid, type, placement) {
      this.request(
        `${this.settings.baseUrl}/${siblingUuid}/insert-sibling/${placement}/${type}`,
        null,
        true,
        false
      );
    }

    insertComponent(type) {
      this.request(
        `${this.settings.baseUrl}/insert-component/${type}`,
        null,
        true,
        false
      );
    }

    /**
     * Inserts a new component into a region.
     * @param {string} parentUuid The parent component's uuid.
     * @param {string} region The region to insert into.
     * @param {string} type The type of component to insert.
     */
    insertComponentIntoRegion(parentUuid, region, type) {
      this.request(
        `${this.settings.baseUrl}/${parentUuid}/insert-into-region/${region}/${type}`,
        null,
        true,
        false
      );
    }

    /**
     * Moves a component up or down.
     * @param {int} direction 1 (down) or -1 (up).
     * @return {void}
     */
    move(direction) {
      const instance = this;
      const $moveItem = this.$activeItem;
      const $sibling =
        direction === 1
          ? $moveItem.nextAll(".lpe-component:visible").first()
          : $moveItem.prevAll(".lpe-component:visible").first();
      const method = direction === 1 ? "after" : "before";
      const { scrollY } = window;
      const destScroll = scrollY + $sibling.outerHeight() * direction;
      const distance = Math.abs(destScroll - scrollY);

      if ($sibling.length === 0) {
        return false;
      }

      this.removeControls();
      this.stopInterval();

      $({ translateY: 0 }).animate(
        { translateY: 100 * direction },
        {
          duration: Math.max(100, Math.min(distance, 500)),
          easing: "swing",
          step() {
            const a = $sibling.outerHeight() * (this.translateY / 100);
            const b = -$moveItem.outerHeight() * (this.translateY / 100);
            $moveItem.css({ transform: `translateY(${a}px)` });
            $sibling.css({ transform: `translateY(${b}px)` });
          },
          complete() {
            $moveItem.css({ transform: "none" });
            $sibling.css({ transform: "none" });
            $sibling[method]($moveItem);
            instance.insertControls($moveItem);
            instance.startInterval();
          }
        }
      );
      if (distance > 50) {
        $("html, body").animate({ scrollTop: destScroll });
      }
      this.edited();
    }

    /**
     * Initiates dragula drag/drop functionality.
     * @param {object} $widget ERL field item to attach drag/drop behavior to.
     * @param {object} widgetSettings The widget instance settings.
     */
    enableDragAndDrop() {
      // this.$element.addClass("dragula-enabled");
      // Turn on drag and drop if dragula function exists.
      if (typeof dragula !== "undefined") {
        const { settings } = this;
        const instance = this;
        const items = this.$element
          .find(".lpe-region")
          .addBack()
          .not(".dragula-enabled")
          .addClass("dragula-enabled")
          .get();

        // Dragula is already initialized, add any new containers that may have been added.
        if (this.$element.data("drake")) {
          Object.values(items).forEach(item => {
            if (this.$element.data("drake").containers.indexOf(item) === -1) {
              this.$element.data("drake").containers.push(item);
            }
          });
          return;
        }
        this.drake = dragula(items, {
          accepts(el, target, source, sibling) {
            // Returns false if any registered callback returns false.
            return (
              Drupal.lpEditorInvokeCallbacks("accepts", {
                el,
                target,
                source,
                sibling
              }).indexOf(false) === -1
            );
          },
          moves(el, source, handle, sibling) {
            const $handle = $(handle);
            if (
              $handle.closest(
                ".lpe-controls,.js-lpe-toggle,.lpe-status,.js-lpe-section-menu"
              ).length
            ) {
              return false;
            }
            return true;
          }
        });
        this.drake.on("drop", el => {
          instance.edited();
        });
        this.drake.on("drag", el => {
          instance.$activeItem = false;
          instance.$element.addClass("is-dragging");
          if (el.className.indexOf("lpe-layout") > -1) {
            instance.$element.addClass("is-dragging-layout");
          } else {
            instance.$element.addClass("is-dragging-item");
          }
        });
        this.drake.on("dragend", el => {
          instance.$element
            .removeClass("is-dragging")
            .removeClass("is-dragging-layout")
            .removeClass("is-dragging-item");
        });
        this.drake.on("over", (el, container) => {
          $(container).addClass("drag-target");
        });
        this.drake.on("out", (el, container) => {
          $(container).removeClass("drag-target");
        });
        this.$element.data("drake", this.drake);
      }
    }

    /**
     * Add new containers to the dragula instance.
     * @param {array} containers The containers to add.
     */
    addDragContainers(containers) {
      containers.forEach(value => {
        if (this.drake.containers.indexOf(value) === -1) {
          this.drake.containers.push(value);
        }
      });
    }

    /**
     * Called after
     */
    saved() {
      this.$banner
        .find(".lpe-save")
        .text(Drupal.t("Save"))
        .hide();
      this.$banner
        .find(".lpe-cancel")
        .text(Drupal.t("Done"))
        .fadeIn();
      setTimeout(() => {
        this.$banner.find(".lp-editor__banner--status").text(Drupal.t(""));
      }, 3000);
    }

    edited() {
      this.$element.find(".lpe-save").show();
      this.$element.find(".lpe-cancel").text(Drupal.t("Cancel"));
      if (this.$element.find(".lpe-component").length > 0) {
        this.isNotEmpty();
      } else {
        this.isEmpty();
      }
    }

    isNotEmpty() {
      console.log("not empty");
      this.$element.find(".js-lpe-empty").remove();
    }

    isEmpty() {
      this.isNotEmpty();
      const $emptyContainer = $(
        `<div class="js-lpe-empty">${this.emptyContainer}</div>`
      ).appendTo(this.$element);
      if (this.settings.requireSections) {
        this.insertSectionMenu($emptyContainer, "insert", "append");
      } else {
        this.insertToggle($emptyContainer, "insert", "append");
      }
    }

    /**
     * Make a Drupal Ajax request.
     * @param {string} url The request url.
     * @param {function} done A callback function to run when done.
     * @param {bool} reorderComponents Whether to reorder all components.
     * @param {bool} deleteTrashBin Whether to delete items in trash.
     */
    request(url, done, reorderComponents = true, deleteTrashBin = true) {
      const submit = {};
      if (reorderComponents) {
        submit.layoutParagraphsState = JSON.stringify(this.getState());
      }
      if (deleteTrashBin) {
        const deleteUuids = this.trashBin.reduce((uuids, $current) => {
          uuids.push($current.attr("data-uuid"));
          $current.find(".lpe-component").each((i, item) => {
            uuids.push($(item).attr("data-uuid"));
          });
          return uuids;
        }, []);
        submit.deleteComponents = JSON.stringify(deleteUuids);
      }
      Drupal.ajax({
        url,
        submit
      })
        .execute()
        .done(() => {
          if (done && typeof done === "function") {
            done.call(this);
          }
        });
    }
  }

  function componentUpdate(layoutId, componentUuid) {
    console.log("componentUpdate", componentUuid);
    const editor = $(`[data-lp-editor-id="${layoutId}"`).data("lpeInstance");
    editor.removeControls();
    const $insertedComponent = $(`[data-uuid="${componentUuid}"]`);
    editor.$activeItem = $insertedComponent;
    const dragContainers = $insertedComponent.find(".lpe-region").get();
    editor.addDragContainers(dragContainers);
    editor.edited();
  }
  /**
   * Registers a callback to be called when a specific hook is invoked.
   * @param {String} hook The name of the hook.
   * @param {function} callback The function to call.
   */
  Drupal.lpEditorRegisterCallback = (hook, callback) => {
    if (Drupal.lpEditorCallbacks === undefined) {
      Drupal.lpEditorCallbacks = [];
    }
    Drupal.lpEditorCallbacks.push({ hook, callback });
  };
  /**
   * Removes a callback from the list.
   * @param {String} hook The name of the hook.
   */
  Drupal.lpEditorUnRegisterCallback = hook => {
    Drupal.lpEditorCallbacks = Drupal.lpEditorCallbacks.filter(
      item => item.hook !== hook
    );
  };
  /**
   * Invoke all callbacks for a specific hook.
   * @param {string} hook The name of the hook.
   * @param {object} param The parameter object which will be passed to the callback.
   * @return {array} an array of returned values from callback functions.
   */
  Drupal.lpEditorInvokeCallbacks = (hook, param) => {
    const applicableCallbacks = Drupal.lpEditorCallbacks.filter(
      item => item.hook.split(".")[0] === hook
    );
    return applicableCallbacks.map(callback =>
      typeof callback.callback === "function" ? callback.callback(param) : null
    );
  };
  Drupal.behaviors.layoutParagraphsEditor = {
    attach: function attach(context, settings) {
      if (settings.layoutParagraphsEditor) {
        Object.values(settings.layoutParagraphsEditor).forEach(
          editorSettings => {
            $(editorSettings.selector)
              .once("lp-editor")
              .each((index, element) => {
                $(element)
                  .addClass("js-lpe-container")
                  .data("lpeInstance", new LPEditor(editorSettings));
              });
          }
        );
      }
      $(".lpe-enable-button").click(e => {
        const $a = $(e.target).closest("a");
        $a.addClass("active").addClass("loading");
        Drupal.ajax({
          url: $a.attr("href")
        }).execute();
        return false;
      });
    }
  };
  Drupal.AjaxCommands.prototype.layoutParagraphsEditorInvokeHook = (
    ajax,
    response,
    status
  ) => {
    Drupal.lpEditorInvokeCallbacks(response.hook, response.params);
  };
  Drupal.lpEditorRegisterCallback("accepts", params => {
    const { el, target, source, sibling } = params;
    // Layout sections can only go at the root level.
    if (el.className.indexOf("lpe-layout") > -1) {
      return target.className.indexOf("lp-editor") > -1;
    }
    if (el.className.indexOf("lpe-component") > -1) {
      return target.className.indexOf("lpe-region") > -1;
    }
  });
  Drupal.lpEditorRegisterCallback("save", layoutId => {
    $(`[data-lp-editor-id="${layoutId}"`)
      .data("lpeInstance")
      .saved();
  });
  Drupal.lpEditorRegisterCallback("updateComponent", params => {
    const { layoutId, componentUuid } = params;
    componentUpdate(layoutId, componentUuid);
  });
  Drupal.lpEditorRegisterCallback("insertComponent", params => {
    const { layoutId, componentUuid } = params;
    console.log("insertComponent", params);
    componentUpdate(layoutId, componentUuid);
  });
})(jQuery, Drupal, drupalSettings, dragula);
