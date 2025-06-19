# TODOS

[X] TODO: The manager agent is not outputing it's plan to the user. We might want to store a json plan on the session record for the manager agent to reference.
[] TODO: The manager agent is only delegating one task at a time. It should be able to delegating multiple tasks at once
[] TODO: How can the copywriter agent write thousands of words without timing out or exhausting it's context window?
[] TODO: Swap line 3 "Always start by sharing your plan and task list with the user, then automatically delegate each task to the appropriate sub-agent." with "Always start by sharing your plan and task list with the user before delegating each task to the appropriate sub-agent."
[] TODO: After "Always start by sharing your plan and task list with the user, then..." prompt the manager agent to ask the user followup questions to refine the plan if it feels like it needs it.
[] TODO: The manager agent is not calling the citation agent.
[] TODO: How do we control the length of a report?
[] TODO: Let's generate some example task scenarios to include in the system prompt.
[] TODO: How do we track the tokens used and model used by each agent in the session messages?
[] TODO: How do we store the task list for the manager agent to reference? Maybe in a column on the session record.
