# Image Credit

This plugin processes all images present in post content, and outputs each image's description field, which is used to display copyright information.  

For performance reasons, since it often has to look up an image's ID by its URI, the plugin creates a lookup table to store found image IDs.  

This table can be pre-populated by running the bundled WP CLI command:
```shell
wp ai index media [--network]
```

## Governance
See [GOVERNANCE.md](GOVERNANCE.md) for project governance information.  

## Changelog  
See [CHANGELOG.md](CHANGELOG.md) or [Releases page](https://github.com/amnestywebsite/humanity-image-credit/releases) for full changelogs.

## Contributing
For information on how to contribute to the project, or to get set up locally for development, please see the documentation in [CONTRIBUTING.md](CONTRIBUTING.md).  

### Special Thanks
We'd like to say a special thank you to these lovely folks:

| &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[Cure53](https://cure53.de)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; | &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;[WP Engine](https://wpengine.com)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; |
| --- | --- |
| ![Cure53](./docs/static/cure_53_logo.svg) | ![WP Engine](./docs/static/wpengine_logo.svg) |


### Want to know more about the work in other Amnesty GitHub accounts?

You can find repositories from other teams such as [Amnesty Web Ops](https://github.com/amnestywebsite), [Amnesty Crisis](https://github.com/amnesty-crisis-evidence-lab), [Amnesty Tech](https://github.com/AmnestyTech), and [Amnesty Research](https://github.com/amnestyresearch/) in their GitHub accounts

![AmnestyWebsiteFooter](https://wordpresstheme.amnesty.org/wp-content/uploads/2024/02/footer.gif)
