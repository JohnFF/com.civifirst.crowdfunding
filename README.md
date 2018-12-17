# com.civifirst.crowdfunding
CiviCRM's CrowdFunding extension: Contributions now have a parent Contribution ID and a "funds accumulated" field. The amount collected on this parent Contribution is updated as Contributions with that parent are submitted. When the full amount is raised the parent contribution's status is updated (pending, partially paid, completed). Any evnet participations relying on that status change are also updated.

To use this: 
1. Create a profile containing the Crowd Funding custom fields.
2. Add it to any Contribution page that you want to enable Crowd Funding on.
3. In Manage ACLs, add permissions for 'view custom field' on Crowd Funding and 'create profile' on Crowd Funding.
4. Add &parent_contribution_id={id} to the URL of any Contribute page.
5. In your template for that page you probably want to hide the Crowd Funding section.
